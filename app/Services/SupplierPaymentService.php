<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\SupplierAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * سداد فواتير الموردين. كل دفعة تُسجَّل في 5 أماكن معاً:
 * الفاتورة (المدفوع/الحالة)، سجل دفعات الفاتورة، رصيد المورد وكشف حسابه، الخزينة (إن كانت نقداً)، والقيد المحاسبي.
 */
class SupplierPaymentService
{
    /** فواتير المورد غير المسددة، الأقدم أولاً */
    public static function openInvoices(Supplier $supplier)
    {
        return Purchase::where('supplier_id', $supplier->id)
            ->whereRaw(Purchase::OPEN_SQL)
            ->orderBy('created_at')->orderBy('id')
            ->get(['id', 'invoice_number', 'branch_id', 'total', 'paid_amount', 'returned_amount', 'created_at']);
    }

    /**
     * دفعة واحدة لمورد تُوزَّع على فواتيره المفتوحة من الأقدم للأحدث.
     *
     * @return array<int, array{invoice: string, amount: float}> ما دُفع على كل فاتورة
     */
    public static function paySupplier(Supplier $supplier, float $amount, string $method, ?int $registerId, User $user, ?string $reference = null, ?string $notes = null): array
    {
        $amount = round($amount, 2);

        return DB::transaction(function () use ($supplier, $amount, $method, $registerId, $user, $reference, $notes) {
            $invoices = self::openInvoices($supplier);
            $due = round($invoices->sum(fn ($p) => $p->remaining()), 2);

            if ($due <= 0) {
                throw ValidationException::withMessages(['amount' => 'لا توجد فواتير غير مسددة لهذا المورد.']);
            }
            if ($amount > $due + 0.001) {
                throw ValidationException::withMessages(['amount' => 'المبلغ أكبر من مجموع المستحق للمورد (' . number_format($due, 2) . ' ₪).']);
            }

            // الخزينة تُفحص مرة واحدة للمبلغ كاملاً
            $register = $method === 'cash'
                ? CashRegisterService::resolve($registerId, $user, $invoices->first()->branch_id, $amount)
                : null;

            $left = $amount;
            $paid = [];
            foreach ($invoices as $invoice) {
                if ($left <= 0) {
                    break;
                }
                $part = round(min($left, $invoice->remaining()), 2);
                self::payInvoice($invoice, $part, $method, $register, $user, $reference, $notes);
                $paid[] = ['invoice' => $invoice->invoice_number, 'amount' => $part];
                $left = round($left - $part, 2);
            }

            return $paid;
        });
    }

    /** دفعة على فاتورة واحدة */
    public static function payInvoice(Purchase $purchase, float $amount, string $method, ?CashRegister $register, User $user, ?string $reference = null, ?string $notes = null): PurchasePayment
    {
        return DB::transaction(function () use ($purchase, $amount, $method, $register, $user, $reference, $notes) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $amount = round($amount, 2);
            $remaining = $purchase->remaining();
            if ($amount <= 0 || $amount > $remaining + 0.001) {
                throw ValidationException::withMessages(['amount' => 'المبلغ أكبر من المتبقي على الفاتورة (' . number_format($remaining, 2) . ').']);
            }

            $isCash = $method === 'cash';
            if ($isCash) {
                $register ??= CashRegisterService::resolve(null, $user, $purchase->branch_id, $amount);
                CashBook::out($register, $amount, 'supplier_payment', "دفعة على فاتورة شراء {$purchase->invoice_number}", 'purchase', $purchase->id);
            }

            $payment = PurchasePayment::create([
                'purchase_id'      => $purchase->id,
                'supplier_id'      => $purchase->supplier_id,
                'cash_register_id' => $isCash ? $register->id : null,
                'user_id'          => $user->id,
                'amount'           => $amount,
                'payment_method'   => $method,
                'reference'        => $reference,
                'notes'            => $notes,
            ]);

            $purchase->paid_amount = round((float) $purchase->paid_amount + $amount, 2);
            $purchase->status = SaleService::statusFor($purchase->netTotal(), (float) $purchase->paid_amount);
            $purchase->save();

            // كشف حساب المورد
            $supplier = Supplier::whereKey($purchase->supplier_id)->lockForUpdate()->first();
            if ($supplier) {
                $supplier->balance = round((float) $supplier->balance - $amount, 2);
                $supplier->save();

                SupplierAccount::create([
                    'supplier_id'      => $supplier->id,
                    'transaction_type' => 'purchase_payment',
                    'debit'            => $amount,
                    'credit'           => 0,
                    'balance'          => $supplier->balance,
                ]);
            }

            $orgId = (int) $purchase->organization_id;
            AccountingService::post($orgId, "دفعة للمورد على فاتورة شراء {$purchase->invoice_number}", [
                ['account_id' => AccountingService::systemAccount(AccountingService::PAYABLES, $orgId)->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => AccountingService::systemAccount($isCash ? AccountingService::CASH : AccountingService::BANK, $orgId)->id, 'debit' => 0, 'credit' => $amount],
            ], AccountingService::nextEntryNumber('PP'), null, 'purchase_payment', $purchase->id);

            return $payment;
        });
    }
}
