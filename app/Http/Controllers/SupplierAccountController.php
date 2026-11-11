<?php

namespace App\Http\Controllers;

use App\Models\SupplierAccount;
use App\Models\Supplier; // استدعاء مودل الموردين
use Illuminate\Http\Request;

class SupplierAccountController extends Controller
{
    public function index()
    {
        $supplierAccounts = SupplierAccount::with('supplier')->latest()->paginate(15);
        $suppliers = Supplier::all(); // جلب قائمة الموردين لعرضها في القائمة المنسدلة
        return view('supplier_accounts.index', compact('supplierAccounts', 'suppliers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id,deleted_at,NULL',
            'transaction_type' => 'required|string',
            'debit' => 'required|numeric|min:0',
            'credit' => 'required|numeric|min:0',
        ]);

        // رصيد المورد = المبلغ المستحق له علينا: الدائن يزيده (مشتريات) والمدين ينقصه (دفعات)
        \Illuminate\Support\Facades\DB::transaction(function () use (&$validated) {
            $supplier = Supplier::whereKey($validated['supplier_id'])->lockForUpdate()->firstOrFail();
            $supplier->balance = round((float) $supplier->balance + (float) $validated['credit'] - (float) $validated['debit'], 2);
            $supplier->save();

            $validated['balance'] = $supplier->balance;
            SupplierAccount::create($validated);
        });

        return back()->with('success', 'تم تسجيل المعاملة المالية للمورد بنجاح.');
    }

    public function show(SupplierAccount $supplierAccount)
    {
        $supplierAccount->load('supplier');
        $statement = SupplierAccount::where('supplier_id', $supplierAccount->supplier_id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(25);

        return view('supplier_accounts.show', compact('supplierAccount', 'statement'));
    }
}
