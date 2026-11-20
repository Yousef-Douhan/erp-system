<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\Organization;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::with('organization')->latest()->paginate(10);
        $organizations = Organization::all();

        return view('suppliers.index', compact('suppliers', 'organizations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id,deleted_at,NULL',
            'name'            => 'required|string|max:255',
            'company_name'    => 'nullable|string|max:255',
            'balance'         => 'required|numeric|min:0',
            'credit_limit'    => 'required|numeric|min:0',
        ]);

        $opening = round((float) $validated['balance'], 2);
        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $opening) {
            $supplier = Supplier::create($validated);

            // الرصيد الافتتاحي (دين قديم للمورد قبل استخدام النظام) يظهر كأول سطر في كشفه
            if ($opening > 0) {
                \App\Models\SupplierAccount::create([
                    'supplier_id' => $supplier->id, 'transaction_type' => 'opening',
                    'debit' => 0, 'credit' => $opening, 'balance' => $opening,
                ]);
                $orgId = (int) $supplier->organization_id;
                \App\Services\AccountingService::post($orgId, 'رصيد افتتاحي للمورد ' . $supplier->name, [
                    ['account_id' => \App\Services\AccountingService::systemAccount(\App\Services\AccountingService::OWNER, $orgId)->id, 'debit' => $opening, 'credit' => 0],
                    ['account_id' => \App\Services\AccountingService::systemAccount(\App\Services\AccountingService::PAYABLES, $orgId)->id, 'debit' => 0, 'credit' => $opening],
                ], \App\Services\AccountingService::nextEntryNumber('SO'), null, 'supplier_opening', $supplier->id);
            }
        });

        return redirect()->route('suppliers.index')
            ->with('success', 'تم إضافة المورد بنجاح.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id,deleted_at,NULL',
            'name'            => 'required|string|max:255',
            'company_name'    => 'nullable|string|max:255',
            'credit_limit'    => 'required|numeric|min:0',
        ]);

        // الرصيد لا يُعدَّل يدوياً: يتغير بفواتير الشراء والدفعات فقط (حتى يطابق كشف الحساب)
        $supplier->update($validated);

        return redirect()->route('suppliers.index')
            ->with('success', 'تم تحديث بيانات المورد بنجاح.');
    }

    public function destroy(Supplier $supplier)
    {
        $balance = round((float) $supplier->balance, 2);
        if ($balance > 0) {
            return back()->with('error', 'لا يمكن حذف مورد له مستحقات علينا (' . number_format($balance, 2) . ' ₪). سدّد فواتيره أولاً.');
        }
        if ($balance < 0) {
            return back()->with('error', 'لا يمكن حذف مورد لنا عنده رصيد (' . number_format(abs($balance), 2) . ' ₪). استرده أو اخصمه من فاتورة قبل الحذف.');
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', 'تم حذف المورد بنجاح.');
    }
}
