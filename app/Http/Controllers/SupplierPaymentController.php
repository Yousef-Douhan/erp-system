<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Services\SupplierPaymentService;
use Illuminate\Http\Request;

/**
 * سداد الموردين: دفعة واحدة لمورد تُوزَّع تلقائياً على فواتيره المفتوحة (الأقدم أولاً)
 * وتُصرف من خزينة (نقداً) أو تُسجَّل كتحويل بنكي.
 */
class SupplierPaymentController extends Controller
{
    public function index(Request $request)
    {
        // المستحق لكل مورد من فواتيره غير المسددة (ضمن صيدلية/فرع المستخدم)
        $dues = Purchase::whereRaw(Purchase::OPEN_SQL)
            ->selectRaw('supplier_id, COUNT(*) AS invoices, SUM' . Purchase::DUE_SQL . ' AS due, MIN(created_at) AS oldest')
            ->groupBy('supplier_id')
            ->get()->keyBy('supplier_id');

        $suppliers = Supplier::whereIn('id', $dues->keys())->orderBy('name')->get(['id', 'name', 'company_name'])
            ->map(function ($s) use ($dues) {
                $s->due = round((float) $dues[$s->id]->due, 2);
                $s->invoices_count = (int) $dues[$s->id]->invoices;
                $s->oldest = $dues[$s->id]->oldest;
                $s->open = SupplierPaymentService::openInvoices($s)->map(fn ($p) => [
                    'number'    => $p->invoice_number,
                    'date'      => $p->created_at?->format('Y-m-d'),
                    'total'     => (float) $p->total,
                    'remaining' => $p->remaining(),
                ])->values();

                return $s;
            })
            ->sortByDesc('due')->values();

        $registers = CashRegister::with('branch:id,name')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'branch_id', 'current_balance']);

        $recent = PurchasePayment::with(['supplier:id,name', 'purchase:id,invoice_number', 'cashRegister:id,name', 'user:id,username'])
            ->whereIn('purchase_id', Purchase::select('id'))
            ->latest()->limit(15)->get();

        $selected = (int) $request->query('supplier');

        return view('supplier_payments.index', compact('suppliers', 'registers', 'recent', 'selected'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id'      => 'required|integer',
            'amount'           => 'required|numeric|min:0.01',
            'payment_method'   => 'required|in:cash,bank',
            'cash_register_id' => 'nullable|required_if:payment_method,cash|integer',
            'reference'        => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:500',
        ], ['cash_register_id.required_if' => 'اختر الخزينة التي سيُصرف منها المبلغ.']);

        // عبر الموديل: المورد والخزينة يجب أن يكونا ضمن صيدلية المستخدم
        $supplier = Supplier::findOrFail($validated['supplier_id']);
        $registerId = null;
        if ($validated['payment_method'] === 'cash') {
            $registerId = CashRegister::where('status', 'active')->findOrFail($validated['cash_register_id'])->id;
        }

        $paid = SupplierPaymentService::paySupplier(
            $supplier, (float) $validated['amount'], $validated['payment_method'], $registerId,
            $request->user(), $validated['reference'] ?? null, $validated['notes'] ?? null
        );

        $list = collect($paid)->map(fn ($p) => $p['invoice'] . ' (' . number_format($p['amount'], 2) . ')')->implode('، ');

        return redirect()->route('supplier-payments.index')
            ->with('success', 'تم دفع ' . number_format((float) $validated['amount'], 2) . ' ₪ للمورد ' . $supplier->name . ' على الفواتير: ' . $list . '.');
    }
}
