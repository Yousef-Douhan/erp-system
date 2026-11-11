<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\AccountingService;
use App\Services\CustomerLedger;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * نقطة البيع: مربوطة بالمنتجات ووحداتها وباركوداتها، الدفعات (FEFO)، الحركات المخزنية،
 * الخزائن، الوصفات الطبية، تنبيهات الأدوية، ديون العملاء والقيود المحاسبية.
 */
class PosController extends Controller
{
    public function index()
    {
        $branchId = $this->currentBranchId(false);
        $today = today()->toDateString();
        // البيع من مستودعات فرع الكاشير فقط
        $branchWarehouses = $branchId ? (\App\Support\Stock::warehouseIdsOfBranch($branchId) ?: [0]) : null;
        $inBranch = fn ($q) => $branchWarehouses ? $q->whereIn('product_batches.warehouse_id', $branchWarehouses) : $q;

        $categories = Category::where('is_active', true)->orderBy('name')->get();

        $products = Product::visibleTo(auth()->user())->where('is_active', true)
            ->with([
                'unit:id,name',
                'productUnits.unit:id,name',
                'productUnits.barcodes:id,product_unit_id,barcode',
                'drugAlerts:id,product_id,severity,message',
            ])
            // الدفعات الصالحة: الكمية والأقرب انتهاءً
            ->withSum(['batches as sellable_batch_qty' => fn ($q) => $inBranch($q)->where('quantity', '>', 0)
                ->where(fn ($w) => $w->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', $today))], 'quantity')
            ->withMin(['batches as nearest_batch_expiry' => fn ($q) => $inBranch($q)->where('quantity', '>', 0)
                ->whereDate('expiry_date', '>=', $today)], 'expiry_date')
            // الدفعات المنتهية (لا تُباع)
            ->withSum(['batches as expired_batch_qty' => fn ($q) => $inBranch($q)->where('quantity', '>', 0)
                ->whereDate('expiry_date', '<', $today)], 'quantity')
            ->withCount(['batches' => fn ($q) => $inBranch($q)])
            ->orderBy('name')
            ->get();

        // الرصيد المعروض = رصيد فرع البيع
        \App\Support\Stock::overlay($products, $branchId);

        $customers = Customer::visibleTo(auth()->user())->with(['prescriptions' => fn ($q) => $q->where('is_filled', false)->latest()])
            ->orderBy('name')
            ->get();

        $paymentMethods = PaymentMethod::where('is_active', true)->get();

        $registers = CashRegister::where('status', 'active')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        // خزينة الوردية المفتوحة تظهر أولاً وتكون الافتراضية
        $shift = \App\Services\CashRegisterService::openShiftFor(auth()->user());
        if ($shift) {
            $registers = $registers->reject(fn ($r) => $r->id === $shift->cash_register_id)
                ->prepend($shift->cashRegister)
                ->filter()
                ->values();
        }

        return view('pos.index', compact('categories', 'products', 'customers', 'paymentMethods', 'registers'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Sale::class);

        $request->validate([
            'customer_id'             => 'nullable|exists:customers,id,deleted_at,NULL',
            'prescription_id'         => 'nullable|exists:prescriptions,id,deleted_at,NULL',
            'cash_register_id'        => 'nullable|exists:cash_registers,id,deleted_at,NULL',
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id,deleted_at,NULL',
            'items.*.product_unit_id' => 'nullable|integer',
            'items.*.quantity'        => 'required|integer|min:1',
            'items.*.discount'        => 'nullable|numeric|min:0',
            'payment_method'          => 'required|string|max:50',
            'paid_amount'             => 'required|numeric|min:0',
            'discount'                => 'nullable|numeric|min:0',
            'wallet_phone'            => 'nullable|string|max:30',
        ]);

        // طريقة الدفع: من الطرق المفعلة، أو "debt" للبيع الآجل، أو "cash" إذا لم تُسجل أي طريقة
        $code = $request->payment_method;
        $method = PaymentMethod::where('code', $code)->first();
        $isBuiltIn = $code === 'debt' || ($code === 'cash' && !PaymentMethod::where('is_active', true)->exists());

        if (!$isBuiltIn && (!$method || !$method->is_active)) {
            return back()->withErrors('طريقة الدفع المختارة غير متاحة.')->withInput();
        }
        if ($code === 'debt' && !$request->filled('customer_id')) {
            return back()->withErrors('البيع الآجل يتطلب اختيار عميل مسجل.')->withInput();
        }
        if ($method && $method->requires_wallet && !$request->filled('wallet_phone')) {
            return back()->withErrors('يجب إدخال رقم المحفظة لطريقة الدفع المختارة.')->withInput();
        }

        // المال يدخل الصندوق النقدي فقط إذا كانت الطريقة نقدية (لا تحتاج محفظة/حساب)
        $isCash = $code === 'cash' || ($method && !$method->requires_wallet && $code !== 'debt');

        try {
            $sale = DB::transaction(function () use ($request, $code, $isCash) {
                $branchId = $this->currentBranchId();
                $orgId = Branch::whereKey($branchId)->value('organization_id')
                    ?? auth()->user()->organization_id
                    ?? Organization::value('id');

                // ---------- تجهيز الأسطر (منتج + وحدة) ----------
                $groups = collect($request->items)
                    ->groupBy(fn ($r) => $r['product_id'] . ':' . ($r['product_unit_id'] ?? 0))
                    ->map(fn ($rows) => [
                        'product_id'      => (int) $rows->first()['product_id'],
                        'product_unit_id' => !empty($rows->first()['product_unit_id']) ? (int) $rows->first()['product_unit_id'] : null,
                        'quantity'        => (int) $rows->sum('quantity'),
                        'discount'        => (float) $rows->sum(fn ($r) => (float) ($r['discount'] ?? 0)),
                    ])
                    ->values();

                // قفل المنتجات لمنع بيع نفس المخزون مرتين في نفس اللحظة
                $products = Product::whereIn('id', $groups->pluck('product_id')->unique())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $lines = [];
                $baseNeeded = [];
                $needsPrescription = [];
                $subtotal = 0;

                foreach ($groups as $g) {
                    $product = $products[$g['product_id']];

                    if (!$product->is_active) {
                        throw ValidationException::withMessages(['items' => "المنتج ({$product->name}) غير مفعل للبيع."]);
                    }

                    if ($product->track_expiry && $product->expiry_date && Carbon::parse($product->expiry_date)->lt(today())
                        && !$product->batches()->exists()) {
                        throw ValidationException::withMessages(['items' => "المنتج ({$product->name}) منتهي الصلاحية ولا يمكن بيعه."]);
                    }

                    // وحدة البيع (علبة/شريط...) وسعرها ومعامل تحويلها للوحدة الأساسية
                    $unit = null;
                    if ($g['product_unit_id']) {
                        $unit = ProductUnit::with('unit')->where('id', $g['product_unit_id'])
                            ->where('product_id', $product->id)->first();
                        if (!$unit) {
                            throw ValidationException::withMessages(['items' => "وحدة البيع المختارة لا تخص المنتج ({$product->name})."]);
                        }
                    }

                    $factor = $unit ? max(0.0001, (float) $unit->conversion_factor) : 1.0;
                    $baseQty = $g['quantity'] * $factor;
                    if (abs($baseQty - round($baseQty)) > 0.0001) {
                        throw ValidationException::withMessages(['items' => "الكمية المطلوبة من ({$product->name}) لا تتوافق مع معامل تحويل الوحدة."]);
                    }
                    $baseQty = (int) round($baseQty);

                    // السعر يُقرأ من قاعدة البيانات وليس من المتصفح
                    $unitPrice = $unit
                        ? ((float) $unit->selling_price > 0 ? (float) $unit->selling_price : (float) $product->selling_price * $factor)
                        : (float) $product->selling_price;

                    $gross = $unitPrice * $g['quantity'];
                    $lineDiscount = min($g['discount'], $gross);

                    $lines[] = [
                        'product'   => $product,
                        'unit'      => $unit,
                        'unit_name' => $unit?->unit?->name ?? $product->unit?->name,
                        'factor'    => $factor,
                        'quantity'  => $g['quantity'],
                        'base_qty'  => $baseQty,
                        'price'     => $unitPrice,
                        'discount'  => $lineDiscount,
                        'total'     => round($gross - $lineDiscount, 2),
                    ];

                    $baseNeeded[$product->id] = ($baseNeeded[$product->id] ?? 0) + $baseQty;
                    if ($product->is_controlled) {
                        $needsPrescription[] = $product->name;
                    }
                    $subtotal += $gross - $lineDiscount;
                }

                // التحقق من المخزون القابل للبيع (بعد استبعاد الدفعات المنتهية)
                foreach ($baseNeeded as $productId => $needed) {
                    $product = $products[$productId];
                    $available = StockService::sellableQuantity($product, $branchId);
                    if ($available < $needed) {
                        throw ValidationException::withMessages([
                            'items' => $available <= 0
                                ? "المنتج ({$product->name}) غير متوفر للبيع حالياً."
                                : "الكمية المطلوبة من ({$product->name}) تتجاوز المتاح للبيع ({$available}).",
                        ]);
                    }
                }

                $customer = $request->customer_id ? Customer::find($request->customer_id) : null;

                // ---------- الوصفة الطبية للأدوية الخاضعة للرقابة ----------
                $prescription = null;
                if ($request->filled('prescription_id')) {
                    $prescription = Prescription::whereKey($request->prescription_id)->lockForUpdate()->first();
                    if (!$customer || !$prescription || (int) $prescription->customer_id !== (int) $customer->id || $prescription->is_filled) {
                        throw ValidationException::withMessages(['prescription_id' => 'الوصفة المختارة غير صالحة لهذا العميل أو تم صرفها مسبقاً.']);
                    }
                }
                if ($needsPrescription && !$prescription) {
                    throw ValidationException::withMessages([
                        'prescription_id' => 'الأصناف التالية تحتاج وصفة طبية: ' . implode('، ', array_unique($needsPrescription)) . '. اختر العميل ووصفته.',
                    ]);
                }

                // ---------- الحسابات ----------
                $discount = min((float) $request->input('discount', 0), $subtotal);
                $grandTotal = round(max(0, $subtotal - $discount), 2);
                $paidInput = $code === 'debt' ? 0 : round((float) $request->paid_amount, 2);

                if (!$customer && $paidInput < $grandTotal) {
                    throw ValidationException::withMessages([
                        'paid_amount' => 'البيع الآجل يتطلب اختيار عميل مسجل. المبلغ المدفوع أقل من إجمالي الفاتورة.',
                    ]);
                }

                // ما زاد عن قيمة الفاتورة يُخصم من الدين القديم للعميل، والباقي "فكة" للزبون
                $paidForInvoice = min($paidInput, $grandTotal);
                $extraForOldDebt = $customer ? min(max(0, $paidInput - $grandTotal), (float) $customer->balance) : 0;
                $remainingDue = round($grandTotal - $paidForInvoice, 2);
                $moneyReceived = round($paidForInvoice + $extraForOldDebt, 2);

                // ---------- الخزينة ----------
                $register = null;
                if ($isCash && $moneyReceived > 0) {
                    $register = $this->resolveRegister($request->cash_register_id, $branchId);
                }

                // ---------- إنشاء الفاتورة ----------
                $sale = Sale::create([
                    'invoice_number'   => 'TMP-' . uniqid('', true),
                    'customer_id'      => $customer?->id,
                    'customer_name'    => $customer ? $customer->name : 'عميل نقدي',
                    'prescription_id'  => $prescription?->id,
                    'branch_id'        => $branchId,
                    'cash_register_id' => $register?->id,
                    'organization_id'  => $orgId,
                    'employee_id'      => auth()->id(),
                    'total'            => $grandTotal,
                    'paid_amount'      => $paidForInvoice,
                    'discount'         => $discount,
                    'status'           => $paidForInvoice >= $grandTotal ? 'paid' : ($paidForInvoice > 0 ? 'partial' : 'unpaid'),
                    'payment_method'   => $code,
                    'wallet_phone'     => $request->wallet_phone,
                ]);

                // رقم فاتورة فريد مبني على رقم السجل
                $sale->update(['invoice_number' => 'INV-' . date('Ymd') . '-' . str_pad($sale->id, 6, '0', STR_PAD_LEFT)]);

                // ---------- الأسطر + صرف المخزون من الدفعات + الحركات المخزنية ----------
                foreach ($lines as $line) {
                    $item = SaleItem::create([
                        'sale_id'         => $sale->id,
                        'product_id'      => $line['product']->id,
                        'product_unit_id' => $line['unit']?->id,
                        'unit_name'       => $line['unit_name'],
                        'unit_factor'     => $line['factor'],
                        'quantity'        => $line['quantity'],
                        'unit_price'      => $line['price'],
                        'discount'        => $line['discount'],
                        'total'           => $line['total'],
                    ]);

                    $batchId = StockService::issueForSaleItem($item, $line['base_qty']);
                    if ($batchId) {
                        $item->update(['batch_id' => $batchId]);
                    }
                }

                // ---------- ديون العميل ----------
                if ($customer && $remainingDue > 0) {
                    CustomerLedger::addDebt($customer, $remainingDue, "متبقي فاتورة رقم {$sale->invoice_number}", 'sale_invoice');
                }
                if ($customer && $extraForOldDebt > 0) {
                    CustomerLedger::recordPayment($customer, $extraForOldDebt, "سداد من دين سابق مع فاتورة رقم {$sale->invoice_number}", 'payment');
                }

                // ---------- الخزينة ----------
                if ($register) {
                    $register->increment('current_balance', $moneyReceived);
                }

                // ---------- الوصفة ----------
                if ($prescription) {
                    $prescription->update(['is_filled' => true]);
                }

                // ---------- القيد المحاسبي ----------
                $this->postSaleEntry($sale, $moneyReceived, $remainingDue, $extraForOldDebt, $isCash);

                return $sale;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors('حدث خطأ أثناء حفظ الفاتورة، يرجى المحاولة مرة أخرى.')->withInput();
        }

        return redirect()->route('pos.index')
            ->with('success', 'تم حفظ الفاتورة رقم ' . $sale->invoice_number . ' بنجاح.')
            ->with('last_sale_id', $sale->id);
    }

    // إضافة عميل سريع من صفحة الـ POS
    public function storeCustomer(Request $request)
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $customer = Customer::create([
            'name'            => $validated['name'],
            'phone'           => $validated['phone'] ?? null,
            'organization_id' => $this->currentOrganizationId(),
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status'   => true,
                'message'  => 'تم إضافة العميل بنجاح',
                'customer' => $customer,
            ]);
        }

        return redirect()->back()->with('success', 'تم إضافة العميل ' . $customer->name . ' بنجاح.');
    }

    /**
     * فرع البيع: فرع المستخدم، أو فرعه في ملف الموظف، أو أول فرع في صيدليته
     */
    private function currentBranchId(bool $required = true): ?int
    {
        // فرع المستخدم، وإلا فرعه في ملف الموظف، وإلا أول فرع في صيدليته
        $branchId = \App\Support\Tenancy::branchId()
            ?? Employee::where('user_id', auth()->id())->value('branch_id')
            ?? \App\Support\Tenancy::defaultBranchId();

        if (!$branchId && $required) {
            throw ValidationException::withMessages(['branch' => 'يجب إنشاء فرع واحد على الأقل قبل البيع.']);
        }

        return $branchId ? (int) $branchId : null;
    }

    /**
     * الخزينة: المختارة من الشاشة، وإلا خزينة وردية الكاشير، وإلا أول خزينة نشطة في الفرع، وإلا أي خزينة نشطة
     */
    private function resolveRegister($registerId, int $branchId): ?CashRegister
    {
        if ($registerId) {
            $register = CashRegister::whereKey($registerId)->where('status', 'active')->lockForUpdate()->first();
            if (!$register) {
                throw ValidationException::withMessages(['cash_register_id' => 'الخزينة المختارة غير نشطة.']);
            }
            return $register;
        }

        // خزينة وردية الكاشير المفتوحة لها الأولوية
        $shift = \App\Services\CashRegisterService::openShiftFor(auth()->user());
        if ($shift) {
            $register = CashRegister::whereKey($shift->cash_register_id)->where('status', 'active')->lockForUpdate()->first();
            if ($register) {
                return $register;
            }
        }

        return CashRegister::where('status', 'active')->where('branch_id', $branchId)->lockForUpdate()->first()
            ?? CashRegister::where('status', 'active')->lockForUpdate()->first();
    }

    /**
     * القيد المحاسبي للبيع:
     * من حـ/ الصندوق أو البنك (المقبوض) + من حـ/ ذمم العملاء (الآجل)
     * إلى حـ/ المبيعات (+ إلى حـ/ ذمم العملاء للجزء المسدد من الدين القديم)
     */
    private function postSaleEntry(Sale $sale, float $moneyReceived, float $remainingDue, float $oldDebtPaid, bool $isCash): void
    {
        if ($sale->total <= 0 && $moneyReceived <= 0) {
            return;
        }

        $orgId = (int) $sale->organization_id;
        $moneyAccount = AccountingService::systemAccount($isCash ? AccountingService::CASH : AccountingService::BANK, $orgId);
        $receivables = AccountingService::systemAccount(AccountingService::RECEIVABLES, $orgId);

        $lines = [
            ['account_id' => $moneyAccount->id, 'debit' => $moneyReceived, 'credit' => 0],
            ['account_id' => $receivables->id, 'debit' => $remainingDue, 'credit' => 0],
            ['account_id' => AccountingService::systemAccount(AccountingService::SALES, $orgId)->id, 'debit' => 0, 'credit' => (float) $sale->total],
        ];
        if ($oldDebtPaid > 0) {
            $lines[] = ['account_id' => $receivables->id, 'debit' => 0, 'credit' => $oldDebtPaid];
        }

        AccountingService::post($orgId, 'فاتورة مبيعات رقم ' . $sale->invoice_number, $lines, AccountingService::nextEntryNumber('SI'), null, 'sale', $sale->id);
    }
}
