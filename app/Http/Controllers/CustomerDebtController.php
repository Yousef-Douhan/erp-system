<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Services\CustomerLedger;
use App\Support\ArabicSearch;
use Illuminate\Http\Request;

class CustomerDebtController extends Controller
{
    // 1. عرض قائمة العملاء وديونهم (الدفتر)
    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);

        $search = $request->input('search');

        $customers = Customer::visibleTo($request->user())->when($search, fn ($query, $search) => ArabicSearch::apply($query, $search, ['name', 'phone']))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // إجمالي ديون كل الزبائن
        $totalAllCustomersDebt = Customer::visibleTo($request->user())->sum('balance');

        return view('customers.debts.index', compact('customers', 'totalAllCustomersDebt'));
    }

    // 2. تسجيل حركة على حساب العميل (سداد أو إضافة دين)
    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'type'   => 'required|in:add,subtract', // add = إضافة دين، subtract = سداد دفعة
        ]);

        // السداد متاح للبائع، أما إضافة دين يدوي (بدون فاتورة) فتحتاج صلاحية تعديل العملاء والديون
        $this->authorize($request->type === 'add' ? 'addDebt' : 'recordPayment', $customer);

        $amount = round((float) $request->amount, 2);

        try {
            if ($request->type === 'add') {
                CustomerLedger::addDebt($customer, $amount, 'إضافة دين يدوي بقيمة ' . number_format($amount, 2) . ' ₪', 'manual_debt');
            } else {
                // السداد نقد يدخل الخزينة فعلاً: دفتر العميل + حركة خزينة + قيد (من حـ/ الصندوق إلى حـ/ ذمم العملاء)
                \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $amount, $request) {
                    CustomerLedger::recordPayment($customer, $amount, 'سداد دفعة بقيمة ' . number_format($amount, 2) . ' ₪', 'payment');

                    $register = \App\Services\CashRegisterService::resolve($request->integer('cash_register_id') ?: null, $request->user(), $customer->branch_id);
                    \App\Services\CashBook::in($register, $amount, 'debt_collection', 'سداد دين: ' . $customer->name, 'customer', $customer->id);

                    $orgId = (int) $customer->organization_id;
                    \App\Services\AccountingService::post($orgId, 'تحصيل دين من ' . $customer->name, [
                        ['account_id' => \App\Services\AccountingService::systemAccount(\App\Services\AccountingService::CASH, $orgId)->id, 'debit' => $amount, 'credit' => 0],
                        ['account_id' => \App\Services\AccountingService::systemAccount(\App\Services\AccountingService::RECEIVABLES, $orgId)->id, 'debit' => 0, 'credit' => $amount],
                    ], \App\Services\AccountingService::nextEntryNumber('CP'), null, 'customer_payment', $customer->id);
                });
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حساب العميل وتسجيل الحركة بنجاح',
            'balance' => (float) $customer->fresh()->balance,
        ]);
    }

    // 3. جلب تفاصيل كشف الحساب (الفواتير + حركات السداد والإضافة)
    public function ledger($id)
    {
        $customer = Customer::findOrFail($id);
        $this->authorize('view', $customer);

        $sales = $customer->sales()->with('items.product:id,name')->latest()->get();

        $payments = CustomerTransaction::where('customer_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'customer' => $customer,
            'sales'    => $sales,
            'payments' => $payments,
        ]);
    }
}
