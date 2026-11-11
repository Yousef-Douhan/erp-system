<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Organization;
use App\Services\CustomerLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    /**
     * عرض قائمة العملاء
     */
    public function index()
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::visibleTo(auth()->user())->with('organization')->latest()->paginate(10);
        $organizations = Organization::all();

        return view('customers.index', compact('customers', 'organizations'));
    }

    /**
     * حفظ عميل جديد (الرصيد المدخل يُسجل كدين افتتاحي في دفتر الديون)
     */
    public function store(Request $request)
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id,deleted_at,NULL',
            'name'            => 'required|string|max:255',
            'phone'           => 'nullable|string|max:50',
            'balance'         => 'nullable|numeric|min:0',
            'credit_limit'    => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            $opening = (float) ($validated['balance'] ?? 0);
            unset($validated['balance']);

            $customer = Customer::create($validated + ['balance' => 0]);

            if ($opening > 0) {
                CustomerLedger::addDebt($customer, $opening, 'رصيد افتتاحي', 'opening_balance');
            }
        });

        return redirect()->route('customers.index')
            ->with('success', 'تم إضافة العميل بنجاح.');
    }

    /**
     * تحديث بيانات عميل (الرصيد لا يُعدل من هنا، بل من دفتر الديون)
     */
    public function update(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id,deleted_at,NULL',
            'name'            => 'required|string|max:255',
            'phone'           => 'nullable|string|max:50',
            'credit_limit'    => 'required|numeric|min:0',
        ]);

        $customer->update($validated);

        return redirect()->route('customers.index')
            ->with('success', 'تم تحديث بيانات العميل بنجاح.');
    }

    /**
     * حذف العميل (ممنوع إذا عليه دين)
     */
    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);

        if ((float) $customer->balance > 0) {
            return redirect()->route('customers.index')
                ->with('error', 'لا يمكن حذف عميل عليه دين (' . number_format($customer->balance, 2) . '). قم بتسوية الدين أولاً.');
        }

        // الفواتير تبقى محفوظة باسم العميل (customer_id يصبح NULL)
        $customer->sales()->whereNull('customer_name')->update(['customer_name' => $customer->name]);
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'تم حذف العميل بنجاح.');
    }
}
