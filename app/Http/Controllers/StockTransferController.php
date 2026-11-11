<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\ProductBatch;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * نقل كميات من دفعات مستودع إلى مستودع آخر (مثلاً من المستودع الرئيسي إلى فرع).
 * الرصيد الكلي للصنف لا يتغير؛ تتغير أماكن الدفعات فقط، مع حركتي "تحويل صادر/وارد".
 */
class StockTransferController extends Controller
{
    public function index()
    {
        $transfers = StockTransfer::with(['fromWarehouse:id,name', 'toWarehouse:id,name', 'user:id,username', 'items.product:id,name', 'items.fromBatch:id,batch_number,expiry_date'])
            ->latest()
            ->paginate(15);

        // المصدر: مستودعات المستخدم. الوجهة: أي مستودع في نفس الصيدلية (فروع أخرى)
        $warehouses = Warehouse::with('branch:id,name')->orderBy('name')->get();
        $targets = \App\Support\Tenancy::bypass(fn () => Warehouse::with('branch:id,name,organization_id')
            ->when(\App\Support\Tenancy::active(), fn ($q) => $q->whereIn('branch_id', \App\Support\Tenancy::orgBranchIds() ?: [0]))
            ->orderBy('name')->get());
        $defaultId = StockService::defaultWarehouseId();

        // الدفعات المتاحة للنقل (الدفعات بلا مستودع تُعامل كأنها في المستودع الرئيسي)
        $batches = ProductBatch::with('product:id,name')
            ->where('quantity', '>', 0)
            ->orderBy('product_id')->orderBy('expiry_date')
            ->get()
            ->map(fn ($b) => [
                'id'        => $b->id,
                'warehouse' => (int) ($b->warehouse_id ?? $defaultId),
                'label'     => ($b->product->name ?? '—') . ' — دفعة ' . $b->batch_number . ($b->expiry_date ? ' (' . $b->expiry_date . ')' : ''),
                'qty'       => (int) $b->quantity,
            ])
            ->values();

        return view('stock_transfers.index', compact('transfers', 'warehouses', 'targets', 'batches'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_warehouse_id'  => 'required|exists:warehouses,id,deleted_at,NULL',
            'to_warehouse_id'    => 'required|exists:warehouses,id,deleted_at,NULL|different:from_warehouse_id',
            'items'              => 'required|array|min:1',
            'items.*.batch_id'   => 'required|exists:product_batches,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'notes'              => 'nullable|string|max:500',
        ], [
            'to_warehouse_id.different' => 'اختر مستودعاً مختلفاً عن مستودع المصدر.',
        ]);

        $transfer = DB::transaction(function () use ($validated, $request) {
            $from = (int) Warehouse::findOrFail($validated['from_warehouse_id'])->id;
            $to = (int) $validated['to_warehouse_id'];

            // الوجهة يجب أن تكون في نفس الصيدلية
            $orgOf = fn ($wid) => \App\Support\Tenancy::bypass(fn () => \App\Models\Branch::whereKey(Warehouse::whereKey($wid)->value('branch_id'))->value('organization_id'));
            if ((int) $orgOf($from) !== (int) $orgOf($to)) {
                throw ValidationException::withMessages(['to_warehouse_id' => 'لا يمكن النقل إلى مستودع في صيدلية أخرى.']);
            }
            $defaultId = StockService::defaultWarehouseId();

            $transfer = StockTransfer::create([
                'transfer_number'   => 'TMP-' . uniqid('', true),
                'from_warehouse_id' => $from,
                'to_warehouse_id'   => $to,
                'user_id'           => $request->user()->id,
                'notes'             => $validated['notes'] ?? null,
            ]);
            $transfer->update(['transfer_number' => 'TRF-' . date('Ymd') . '-' . str_pad($transfer->id, 4, '0', STR_PAD_LEFT)]);

            foreach (collect($validated['items'])->groupBy('batch_id') as $batchId => $rows) {
                $qty = (int) $rows->sum('quantity');
                $batch = ProductBatch::with('product:id,name')->whereKey($batchId)->lockForUpdate()->firstOrFail();

                if ((int) ($batch->warehouse_id ?? $defaultId) !== $from) {
                    throw ValidationException::withMessages(['items' => "الدفعة {$batch->batch_number} ليست في مستودع المصدر."]);
                }
                if ($qty > (int) $batch->quantity) {
                    throw ValidationException::withMessages(['items' => 'الكمية المنقولة من (' . ($batch->product->name ?? '') . ") أكبر من المتوفر في الدفعة ({$batch->quantity})."]);
                }

                $batch->decrement('quantity', $qty);

                $target = ProductBatch::withoutGlobalScopes()->where('product_id', $batch->product_id)
                    ->where('warehouse_id', $to)
                    ->where('batch_number', $batch->batch_number)
                    ->when($batch->expiry_date, fn ($q) => $q->whereDate('expiry_date', $batch->expiry_date), fn ($q) => $q->whereNull('expiry_date'))
                    ->lockForUpdate()
                    ->first();

                if ($target) {
                    $target->increment('quantity', $qty);
                } else {
                    $target = ProductBatch::withoutGlobalScopes()->create([
                        'product_id'    => $batch->product_id,
                        'warehouse_id'  => $to,
                        'batch_number'  => $batch->batch_number,
                        'expiry_date'   => $batch->expiry_date,
                        'cost_price'    => $batch->cost_price,
                        'selling_price' => $batch->selling_price,
                        'quantity'      => $qty,
                    ]);
                }

                $transfer->items()->create([
                    'product_id'    => $batch->product_id,
                    'from_batch_id' => $batch->id,
                    'to_batch_id'   => $target->id,
                    'quantity'      => $qty,
                ]);

                // حركتان متعاكستان (الوارد أولاً حتى لا يظهر نقص مؤقت): الرصيد الكلي للصنف لا يتغير
                foreach ([[$to, $target->id, $qty, 0, 'transfer_in'], [$from, $batch->id, 0, $qty, 'transfer_out']] as [$wh, $bId, $in, $out, $type]) {
                    InventoryTransaction::create([
                        'warehouse_id'   => $wh,
                        'product_id'     => $batch->product_id,
                        'batch_id'       => $bId,
                        'type'           => $type,
                        'reference_type' => 'stock_transfer',
                        'reference_id'   => $transfer->id,
                        'qty_in'         => $in,
                        'qty_out'        => $out,
                    ]);
                }
            }

            return $transfer;
        });

        return back()->with('success', "تم تنفيذ التحويل {$transfer->transfer_number}.");
    }
}
