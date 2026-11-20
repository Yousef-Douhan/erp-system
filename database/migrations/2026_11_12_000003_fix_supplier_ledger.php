<?php

use App\Services\AccountingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تصحيح: فواتير الشراء القديمة لم تكن تُضاف لرصيد المورد ولا لكشفه ولا للقيود (الدفعات فقط كانت تُخصم).
 * هنا تُضاف كل فاتورة لكشف موردها في تاريخها، ويُعاد حساب الرصيد بعد كل حركة، ويُنشأ قيدها المحاسبي.
 * آمنة للتكرار: الفاتورة التي لها سطر purchase_invoice أو قيد purchase لا تُضاف مرة ثانية.
 */
return new class extends Migration
{
    public function up(): void
    {
        $purchases = DB::table('purchases')->whereNull('deleted_at')->where('total', '>', 0)->orderBy('created_at')->orderBy('id')->get();
        if ($purchases->isEmpty()) {
            return;
        }

        $alreadyPosted = DB::table('journal_entries')->where('ref_type', 'purchase')->pluck('ref_id')->flip();

        foreach ($purchases->groupBy('supplier_id') as $supplierId => $rows) {
            $supplier = DB::table('suppliers')->where('id', $supplierId)->first();
            if (!$supplier || DB::table('supplier_accounts')->where('supplier_id', $supplierId)->where('transaction_type', 'purchase_invoice')->exists()) {
                continue;
            }

            // الرصيد الافتتاحي = الرصيد الحالي مع إلغاء أثر الحركات المسجلة
            $existing = DB::table('supplier_accounts')->where('supplier_id', $supplierId);
            $opening = round((float) $supplier->balance + (float) (clone $existing)->sum('debit') - (float) (clone $existing)->sum('credit'), 2);

            foreach ($rows as $p) {
                DB::table('supplier_accounts')->insert([
                    'supplier_id'      => $supplierId,
                    'transaction_type' => 'purchase_invoice',
                    'debit'            => 0,
                    'credit'           => (float) $p->total,
                    'balance'          => 0,
                    'created_at'       => $p->created_at,
                    'updated_at'       => $p->created_at,
                ]);
            }

            $balance = $opening;
            DB::table('supplier_accounts')->where('supplier_id', $supplierId)->orderBy('created_at')->orderBy('id')->get()
                ->each(function ($row) use (&$balance) {
                    $balance = round($balance + (float) $row->credit - (float) $row->debit, 2);
                    DB::table('supplier_accounts')->where('id', $row->id)->update(['balance' => $balance]);
                });

            DB::table('suppliers')->where('id', $supplierId)->update(['balance' => $balance]);
        }

        // القيود: من حـ/ المشتريات إلى حـ/ ذمم الموردين، بتاريخ كل فاتورة
        foreach ($purchases as $p) {
            if (isset($alreadyPosted[$p->id]) || !$p->organization_id) {
                continue;
            }
            $orgId = (int) $p->organization_id;
            AccountingService::post($orgId, 'فاتورة شراء ' . $p->invoice_number, [
                ['account_id' => AccountingService::systemAccount(AccountingService::PURCHASES, $orgId)->id, 'debit' => (float) $p->total, 'credit' => 0],
                ['account_id' => AccountingService::systemAccount(AccountingService::PAYABLES, $orgId)->id, 'debit' => 0, 'credit' => (float) $p->total],
            ], AccountingService::nextEntryNumber('PI'), substr((string) $p->created_at, 0, 10), 'purchase', $p->id);
        }
    }

    public function down(): void
    {
    }
};
