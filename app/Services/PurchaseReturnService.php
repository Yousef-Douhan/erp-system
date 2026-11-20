<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierAccount;
use App\Models\User;
use App\Support\Stock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * مرتجع مشتريات للمورد (أدوية تالفة / قريبة الانتهاء / خطأ في الطلب):
 * 1) الكمية تخرج من نفس دفعة الشراء في مستودع فرع الفاتورة (ولا يُسمح بإرجاع ما بِيع)
 * 2) القيمة = الكمية × تكلفة الشراء على السطر
 * 3) تُخصم أولاً من المتبقي علينا في الفاتورة. إذا كانت الفاتورة مسددة، الزائد إما:
 *    - «cash»: المورد يرجّعه نقداً → يدخل الخزينة
 *    - «credit»: يبقى رصيداً لنا عند المورد (رصيده ينزل تحت الصفر) ويُخصم من فواتيره القادمة
 * 4) كشف المورد + القيد: من حـ/ ذمم الموردين (أو الصندوق)  إلى حـ/ مردودات المشتريات
 */
class PurchaseReturnService
{
    /**
     * @param array<int, int|string> $quantities [purchase_item_id => الكمية المرتجعة]
     * @param string $excessMode  cash | credit — مصير ما زاد عن المتبقي على الفاتورة
     */
    public static function create(Purchase $purchase, array $quantities, string $excessMode, ?int $registerId, ?string $reason, User $user): PurchaseReturn
    {
        return DB::transaction(function () use ($purchase, $quantities, $excessMode, $registerId, $reason, $user) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $items = PurchaseItem::where('purchase_id', $purchase->id)->lockForUpdate()->get()->keyBy('id');
            $warehouseId = Stock::warehouseFor((int) $purchase->branch_id);

            $lines = [];
            foreach ($quantities as $itemId => $qty) {
                $qty = (int) $qty;
                if ($qty <= 0) {
                    continue;
                }

                $item = $items[(int) $itemId] ?? null;
                if (!$item) {
                    throw ValidationException::withMessages(['items' => 'أحد الأصناف المختارة لا يتبع هذه الفاتورة.']);
                }

                $name = $item->product->name ?? 'صنف';
                if ($qty > $item->returnableQuantity()) {
                    throw ValidationException::withMessages([
                        'items' => "الكمية المرتجعة من ({$name}) أكبر من المتبقي في الفاتورة (" . $item->returnableQuantity() . ').',
                    ]);
                }

                // الدفعة التي دخلت بها البضاعة، ولا نرجّع أكثر مما بقي فيها فعلاً
                $batch = null;
                if ($item->batch_number) {
                    $batch = ProductBatch::withoutGlobalScopes()
                        ->where('product_id', $item->product_id)
                        ->where('warehouse_id', $warehouseId)
                        ->where('batch_number', $item->batch_number)
                        ->lockForUpdate()->first();
                    $inBatch = (int) ($batch->quantity ?? 0);
                    if ($inBatch < $qty) {
                        throw ValidationException::withMessages([
                            'items' => "الموجود من ({$name}) في الدفعة {$item->batch_number} هو {$inBatch} فقط (الباقي بِيع أو نُقل).",
                        ]);
                    }
                }

                $lines[] = [
                    'item'  => $item,
                    'qty'   => $qty,
                    'batch' => $batch,
                    'cost'  => (float) $item->unit_cost,
                    'total' => round($qty * (float) $item->unit_cost, 2),
                ];
            }

            if (!$lines) {
                throw ValidationException::withMessages(['items' => 'اختر صنفاً واحداً على الأقل وحدد الكمية المرتجعة.']);
            }

            $total = min(round(array_sum(array_column($lines, 'total')), 2), $purchase->netTotal());

            // ---------- التوزيع: المتبقي على الفاتورة أولاً، ثم الزائد ----------
            $debt = round(min($total, $purchase->remaining()), 2);
            $excess = round($total - $debt, 2);
            $cash = $excessMode === 'cash' ? $excess : 0.0;
            $credit = $excessMode === 'cash' ? 0.0 : $excess;

            $register = null;
            if ($cash > 0) {
                if (!$registerId) {
                    throw ValidationException::withMessages(['cash_register_id' => 'اختر الخزينة التي سيدخلها المبلغ المسترد من المورد.']);
                }
                $register = CashRegisterService::resolve($registerId, $user, $purchase->branch_id);
            }

            // ---------- المرتجع ----------
            $return = PurchaseReturn::create([
                'organization_id'  => $purchase->organization_id,
                'branch_id'        => $purchase->branch_id,
                'purchase_id'      => $purchase->id,
                'supplier_id'      => $purchase->supplier_id,
                'user_id'          => $user->id,
                'cash_register_id' => $register?->id,
                'return_number'    => 'TMP-' . uniqid('', true),
                'total'            => $total,
                'debt_reduction'   => $debt,
                'cash_refund'      => $cash,
                'supplier_credit'  => $credit,
                'reason'           => $reason,
            ]);
            $return->update(['return_number' => 'PRT-' . date('Ymd') . '-' . str_pad($return->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($lines as $line) {
                $returnItem = $return->items()->create([
                    'purchase_item_id' => $line['item']->id,
                    'product_id'       => $line['item']->product_id,
                    'batch_id'         => $line['batch']?->id,
                    'quantity'         => $line['qty'],
                    'unit_cost'        => $line['cost'],
                    'total'            => $line['total'],
                ]);

                $line['item']->increment('returned_quantity', $line['qty']);

                if ($line['batch']) {
                    $line['batch']->decrement('quantity', $line['qty']);
                    InventoryTransaction::create([
                        'warehouse_id'   => $warehouseId,
                        'product_id'     => $line['item']->product_id,
                        'batch_id'       => $line['batch']->id,
                        'type'           => 'purchase_return',
                        'reference_type' => 'purchase_return_item',
                        'reference_id'   => $returnItem->id,
                        'qty_in'         => 0,
                        'qty_out'        => $line['qty'],
                    ]);
                } else {
                    // سطر بدون رقم دفعة: يخرج من رصيد الفرع (ويُرفض إذا لم يكفِ)
                    Stock::move((int) $line['item']->product_id, (int) $purchase->branch_id, -$line['qty'], 'purchase_return', 'purchase_return_item', $returnItem->id);
                }
            }

            // ---------- الفاتورة ----------
            $purchase->returned_amount = round((float) $purchase->returned_amount + $total, 2);
            if ($cash > 0) {
                // المورد ردّ جزءاً مما دفعناه: صافي ما دفعناه على الفاتورة ينقص بنفس المبلغ
                $purchase->paid_amount = round(max(0, (float) $purchase->paid_amount - $cash), 2);
            }
            $purchase->status = SaleService::statusFor($purchase->netTotal(), (float) $purchase->paid_amount);
            $purchase->save();

            // ---------- رصيد المورد وكشفه (النقد المسترد لا يغير ما علينا) ----------
            $owedChange = round($debt + $credit, 2);
            $supplier = Supplier::withoutGlobalScope('tenant')->whereKey($purchase->supplier_id)->lockForUpdate()->first();
            if ($supplier && $owedChange > 0) {
                $supplier->balance = round((float) $supplier->balance - $owedChange, 2);
                $supplier->save();

                SupplierAccount::create([
                    'supplier_id'      => $supplier->id,
                    'transaction_type' => 'purchase_return',
                    'debit'            => $owedChange,
                    'credit'           => 0,
                    'balance'          => $supplier->balance,
                ]);
            }

            if ($register) {
                CashBook::in($register, $cash, 'supplier_refund', "استرداد من المورد — مرتجع {$return->return_number}", 'purchase_return', $return->id);
            }

            // ---------- القيد ----------
            $orgId = (int) $purchase->organization_id;
            AccountingService::post($orgId, "مرتجع مشتريات {$return->return_number} من فاتورة {$purchase->invoice_number}", [
                ['account_id' => AccountingService::systemAccount(AccountingService::PAYABLES, $orgId)->id, 'debit' => $owedChange, 'credit' => 0],
                ['account_id' => AccountingService::systemAccount(AccountingService::CASH, $orgId)->id, 'debit' => $cash, 'credit' => 0],
                ['account_id' => AccountingService::systemAccount(AccountingService::PURCHASE_RETURNS, $orgId)->id, 'debit' => 0, 'credit' => $total],
            ], AccountingService::nextEntryNumber('PR'), null, 'purchase_return', $return->id);

            return $return;
        });
    }
}
