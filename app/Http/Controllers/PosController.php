<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    public function index()
    {
        $categories = Category::all();
        $products = Product::all();
        $customers = Customer::all();

        // جلب طرق الدفع الفعالة من الجدول
        $paymentMethods = PaymentMethod::where('is_active', true)->get();

        return view('pos.index', compact('categories', 'products', 'customers', 'paymentMethods'));
    }

    public function store(Request $request)
    {
        // التحقق من صحة البيانات الواردة من الطلب
        $request->validate([
            'customer_id'       => 'nullable|exists:customers,id',
            'items'             => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'payment_method'     => 'required|string',
            'paid_amount'        => 'required|numeric|min:0',
            'discount'           => 'nullable|numeric|min:0',
            'wallet_phone'       => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // التحقق المسبق من المخزون لكل منتج باستخدام stock_quantity
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
                    throw new \Exception('المنتج غير موجود.');
                }

                $requestedQty = $item['quantity'];
                $availableStock = $product->stock_quantity ?? 0;

                if ($availableStock <= 0) {
                    throw new \Exception("المنتج ({$product->name}) نفد من المخزون تماماً!");
                }

                if ($requestedQty > $availableStock) {
                    throw new \Exception("الكمية المطلوبة للمنتج ({$product->name}) تتجاوز المخزون المتاح حالياً (المتاح: {$availableStock})!");
                }
            }

            // حساب المجموع الفرعي
            $subtotal = 0;
            foreach ($request->items as $item) {
                $subtotal += ($item['unit_price'] * $item['quantity']);
            }

            $discount = $request->input('discount', 0);
            $grandTotal = max(0, $subtotal - $discount);
            $paidAmount = $request->paid_amount ?? 0;

            // حساب المبلغ المؤجل (الذي لم يتم دفعه)
            $remainingDue = $grandTotal - $paidAmount;

            // تحديد حالة الدفع (مدفوعة بالكامل، جزئي، أو غير مدفوعة)
            $status = 'unpaid';
            if ($paidAmount >= $grandTotal) {
                $status = 'paid';
            } elseif ($paidAmount > 0) {
                $status = 'partial';
            }

            // إنشاء الفاتورة الأساسية
            $sale = Sale::create([
                'invoice_number'   => 'INV-' . date('Ymd-His'),
                'customer_id'      => $request->customer_id ?: null,
                'customer_name'    => $request->customer_id ? null : 'عميل نقدي',
                'branch_id'        => auth()->user()->branch_id ?? 1,
                'organization_id'  => auth()->user()->organization_id ?? 1,
                'employee_id'      => auth()->id(),
                'total'            => $grandTotal,
                'total_amount'     => $grandTotal, // توافقاً مع الأعمدة المختلفة في الجداول
                'paid_amount'      => $paidAmount,
                'remaining_amount' => max(0, $remainingDue),
                'discount'         => $discount,
                'status'           => $status,
                'payment_method'   => $request->payment_method,
                'wallet_phone'     => $request->wallet_phone,
            ]);

            // حفظ عناصر الفاتورة وخصم الكميات من المخزون بأمان
            foreach ($request->items as $item) {
                $itemDiscount = $item['discount'] ?? 0;
                $itemTotal = ($item['unit_price'] * $item['quantity']) - $itemDiscount;

                SaleItem::create([
                    'sale_id'    => $sale->id,
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount'   => $itemDiscount,
                    'total'      => $itemTotal,
                ]);

                // خصم المخزون باستخدام العمود الصحيح stock_quantity
                $product = Product::find($item['product_id']);
                if ($product) {
                    $product->decrement('stock_quantity', $item['quantity']);
                }
            }

            // إذا تم اختيار عميل وكان هناك متبقي (دين جديد)، قم بإضافته لدفتر ديونه
            if ($request->filled('customer_id') && $remainingDue > 0) {
                $customer = Customer::find($request->customer_id);
                if ($customer) {
                    $customer->balance += $remainingDue; // ترحيل المبلغ للدين القديم
                    $customer->save();
                }
            }

            DB::commit();

            return redirect()->route('pos.index')->with('success', 'تمت عملية البيع وترحيل الدين بنجاح برقم: ' . $sale->invoice_number);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors('حدث خطأ أثناء حفظ الفاتورة: ' . $e->getMessage())->withInput();
        }
    }

    // دالة إضافة العميل السريعة من صفحة الـ POS لتجنب خطأ Method does not exist
    public function storeCustomer(Request $request)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'nullable|string|max:50',
            'email'   => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        try {
            $customer = Customer::create([
                'name'            => $request->name,
                'phone'           => $request->phone,
                'email'           => $request->email,
                'address'         => $request->address,
                'organization_id' => auth()->user()->organization_id ?? 1,
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status'   => true,
                    'message'  => 'تم إضافة العميل بنجاح',
                    'customer' => $customer
                ]);
            }

            return redirect()->back()->with('success', 'تم إضافة العميل بنجاح');
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'حدث خطأ أثناء إضافة العميل: ' . $e->getMessage()
                ], 422);
            }

            return redirect()->back()->withErrors('حدث خطأ أثناء إضافة العميل: ' . $e->getMessage())->withInput();
        }
    }
}
