<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * عرض قائمة العملاء
     */
    public function index()
    {
        $customers = Customer::with('organization')->latest()->paginate(10);
        $organizations = Organization::all();

        return view('customers.index', compact('customers', 'organizations'));
    }

    /**
     * حفظ عميل جديد
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name'            => 'required|string|max:255',
            'phone'           => 'nullable|string|max:50',
            'balance'         => 'required|numeric|min:0',
            'credit_limit'    => 'required|numeric|min:0',
        ]);

        Customer::create($validated);

        return redirect()->route('customers.index')
            ->with('success', 'تم إضافة العميل بنجاح.');
    }

    /**
     * تحديث بيانات عميل
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name'            => 'required|string|max:255',
            'phone'           => 'nullable|string|max:50',
            'balance'         => 'required|numeric|min:0',
            'credit_limit'    => 'required|numeric|min:0',
        ]);

        $customer->update($validated);

        return redirect()->route('customers.index')
            ->with('success', 'تم تحديث بيانات العميل بنجاح.');
    }

    /**
     * حذف العميل
     */
    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'تم حذف العميل بنجاح.');
    }

    public function ledger($id)
    {
        $customer = Customer::with(['sales.items.product', 'sales.items'])
            ->findOrFail($id);

        // جلب جميع فواتير العميل مع تفاصيل الأدوية المرتبطة بها مرتبة من الأحدث للأقدم
        $sales = $customer->sales()
            ->with('items.product')
            ->latest()
            ->get();

        return response()->json([
            'customer' => $customer,
            'sales' => $sales
        ]);
    }
}
