<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\ProductBatch;
use App\Models\StockDisposal;
use App\Support\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * إتلاف المخزون (منتهي الصلاحية / تالف): يخرج من الدفعة ومن رصيد الفرع بحركة «إتلاف»،
 * ويُسجَّل كخسارة بسعر التكلفة تظهر في تقرير الأرباح. لا يُحذف ولا يُعدَّل.
 */
class StockDisposalController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = [
            $request->date('from') ?? today()->startOfMonth(),
            $request->date('to') ?? today(),
        ];

        $query = StockDisposal::with(['product:id,name', 'batch:id,batch_number,expiry_date', 'branch:id,name', 'user:id,username'])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->when($request->filled('reason'), fn ($q) => $q->where('reason', $request->query('reason')));

        $totals = (clone $query)->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(quantity), 0) AS qty, COALESCE(SUM(total_cost), 0) AS cost')->first();
        $disposals = $query->latest()->paginate(25)->withQueryString();
        $reasons = StockDisposal::REASONS;

        return view('disposals.index', compact('disposals', 'totals', 'reasons', 'from', 'to'));
    }

    public function store(Request $request, ProductBatch $batch)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'reason'   => ['required', Rule::in(array_keys(StockDisposal::REASONS))],
            'notes'    => 'nullable|string|max:255',
        ]);

        $disposal = DB::transaction(function () use ($batch, $validated, $request) {
            $batch = ProductBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $qty = (int) $validated['quantity'];
            $name = $batch->product->name ?? 'الصنف';

            if ($qty > (int) $batch->quantity) {
                throw ValidationException::withMessages(['quantity' => "الموجود من ({$name}) في هذه الدفعة {$batch->quantity} فقط."]);
            }

            $branchId = Stock::branchOfWarehouse((int) $batch->warehouse_id);
            $unitCost = (float) $batch->cost_price > 0 ? (float) $batch->cost_price : (float) ($batch->product->purchase_price ?? 0);

            $disposal = StockDisposal::create([
                'organization_id' => $batch->product->organization_id ?? null,
                'branch_id'       => $branchId,
                'warehouse_id'    => $batch->warehouse_id,
                'product_id'      => $batch->product_id,
                'batch_id'        => $batch->id,
                'user_id'         => $request->user()->id,
                'quantity'        => $qty,
                'unit_cost'       => $unitCost,
                'total_cost'      => round($qty * $unitCost, 2),
                'reason'          => $validated['reason'],
                'notes'           => $validated['notes'] ?? null,
            ]);

            $batch->decrement('quantity', $qty);

            InventoryTransaction::create([
                'warehouse_id'   => $batch->warehouse_id,
                'product_id'     => $batch->product_id,
                'batch_id'       => $batch->id,
                'type'           => 'disposal',
                'reference_type' => 'stock_disposal',
                'reference_id'   => $disposal->id,
                'qty_in'         => 0,
                'qty_out'        => $qty,
            ]);

            return $disposal;
        });

        return back()->with('success', 'تم إتلاف ' . $disposal->quantity . ' من «' . ($disposal->product->name ?? '') . '» بتكلفة ' . number_format((float) $disposal->total_cost, 2) . ' ₪.');
    }
}
