<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerDebtController extends Controller
{
    // 1. عرض قائمة العملاء وديونهم (الدفتر)
    public function index(Request $request)
    {
        $search = $request->input('search');

        $customers = Customer::when($search, function ($query, $search) {
            return $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // إجمالي ديون كل الزبائن
        $totalAllCustomersDebt = Customer::sum('balance');

        return view('customers.debts.index', compact('customers', 'totalAllCustomersDebt'));
    }

    // 2. تحديث الرصيد وتسجيل الحركة (سداد أو إضافة دين)
    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:add,subtract', // add = إضافة دين، subtract = سداد دفعة
        ]);

        DB::transaction(function () use ($request, $customer) {
            if ($request->type === 'add') {
                $customer->balance += $request->amount;
                $notes = 'إضافة دين يدوي بقيمة ' . $request->amount . ' ₪';
            } else {
                $customer->balance -= $request->amount;
                if ($customer->balance < 0) {
                    $customer->balance = 0;
                }
                $notes = 'سداد دفعة بقيمة ' . $request->amount . ' ₪';
            }

            $customer->save();

            // حفظ الحركة في جدول الحركات لكي تظهر في كشف الحساب مع التاريخ
            DB::table('customer_transactions')->insert([
                'customer_id' => $customer->id,
                'type' => $request->type,
                'amount' => $request->amount,
                'notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حساب العميل وتسجيل الحركة بنجاح'
        ]);
    }

    // 3. جلب تفاصيل كشف الحساب (الفواتير + حركات السداد والإضافة)
    public function ledger($id)
    {
        $customer = Customer::with(['sales.items.product'])->findOrFail($id);

        // جلب الحركات والسدادات الخاصة بالعميل مرتبة من الأحدث
        $payments = DB::table('customer_transactions')
            ->where('customer_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'customer' => $customer,
            'sales' => $customer->sales ?? [],
            'payments' => $payments ?? []
        ]);
    }
}
