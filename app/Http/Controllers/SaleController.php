<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Organization;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $query = Sale::with(['organization', 'branch', 'customer', 'employee']);

        // فلترة المبيعات حسب التاريخ (من - إلى)
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // فلترة سريعة لمبيعات اليوم بنقرة زر
        if ($request->has('filter') && $request->filter == 'today') {
            $query->whereDate('created_at', today());
        }

        // جلب جميع البيانات دفعة واحدة بدون تقطيع صفحات
        $sales = $query->latest()->get();

        $organizations = Organization::all();
        $branches = Branch::all();
        $customers = Customer::all();
        $employees = User::all();

        return view('sales.index', compact('sales', 'organizations', 'branches', 'customers', 'employees'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'branch_id'       => 'required|exists:branches,id',
            'customer_id'     => 'required|exists:customers,id',
            'employee_id'     => 'required|exists:users,id',
            'invoice_number'  => 'required|string|unique:sales,invoice_number|max:255',
            'total'           => 'required|numeric|min:0',
            'paid_amount'     => 'required|numeric|min:0|lte:total',
            'status'          => 'required|in:paid,partial,unpaid',
        ]);

        Sale::create($validated);

        return redirect()->route('sales.index')
            ->with('success', 'تم تسجيل فاتورة المبيعات بنجاح.');
    }

    public function update(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'branch_id'       => 'required|exists:branches,id',
            'customer_id'     => 'required|exists:customers,id',
            'employee_id'     => 'required|exists:users,id',
            'invoice_number'  => 'required|string|max:255|unique:sales,invoice_number,' . $sale->id,
            'total'           => 'required|numeric|min:0',
            'paid_amount'     => 'required|numeric|min:0|lte:total',
            'status'          => 'required|in:paid,partial,unpaid',
        ]);

        $sale->update($validated);

        return redirect()->route('sales.index')
            ->with('success', 'تم تحديث فاتورة المبيعات بنجاح.');
    }

    public function destroy(Sale $sale)
    {
        $sale->delete();

        return redirect()->route('sales.index')
            ->with('success', 'تم حذف فاتورة المبيعات بنجاح.');
    }


    public function show(Sale $sale)
    {
        // جلب الفاتورة مع العلاقات المطلوبة وعناصرها والمنتجات المتاحة للاختيار
        $sale->load(['organization', 'branch', 'customer', 'employee', 'items.product']);
        $products = \App\Models\Product::all(); // لجلب المنتجات في قائمة الإضافة

        return view('sales.show', compact('sale', 'products'));
    }

    public function pay(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . ($sale->total - $sale->paid_amount),
        ]);

        $newPaidAmount = $sale->paid_amount + $validated['amount'];

        // تحديد حالة الدفع تلقائياً
        $status = 'partial';
        if ($newPaidAmount >= $sale->total) {
            $status = 'paid';
        }

        $sale->update([
            'paid_amount' => $newPaidAmount,
            'status' => $status
        ]);

        return back()->with('success', 'تم تسجيل دفعة بقيمة ' . number_format($validated['amount'], 2) . ' بنجاح.');
    }
}
