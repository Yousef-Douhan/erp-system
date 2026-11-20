<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierAccount;

/**
 * كشف حساب المورد: فواتير الشراء (له) والدفعات (منه) والرصيد بعد كل حركة.
 */
class SupplierAccountController extends Controller
{
    public function show(Supplier $supplier)
    {
        $statement = SupplierAccount::where('supplier_id', $supplier->id)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(40);

        $totals = SupplierAccount::where('supplier_id', $supplier->id)
            ->selectRaw("COALESCE(SUM(credit), 0) AS invoices,
                COALESCE(SUM(CASE WHEN transaction_type = 'purchase_return' THEN 0 ELSE debit END), 0) AS payments,
                COALESCE(SUM(CASE WHEN transaction_type = 'purchase_return' THEN debit ELSE 0 END), 0) AS returns")
            ->first();

        $openInvoices = \App\Services\SupplierPaymentService::openInvoices($supplier);

        return view('supplier_accounts.show', compact('supplier', 'statement', 'totals', 'openInvoices'));
    }
}
