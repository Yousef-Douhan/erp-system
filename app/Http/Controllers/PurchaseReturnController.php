<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Services\PurchaseReturnService;
use App\Support\Stock;
use Illuminate\Http\Request;

/**
 * مرتجعات المشتريات للمورد: تُسجَّل من فاتورة الشراء نفسها.
 */
class PurchaseReturnController extends Controller
{
    public function index(Request $request)
    {
        $returns = PurchaseReturn::with(['purchase:id,invoice_number', 'supplier:id,name', 'user:id,username'])
            ->withCount('items')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim($request->q);
                $q->where(fn ($w) => $w->where('return_number', 'like', "%{$term}%")
                    ->orWhereHas('purchase', fn ($p) => $p->where('invoice_number', 'like', "%{$term}%"))
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$term}%")));
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $totals = PurchaseReturn::whereDate('created_at', '>=', today()->startOfMonth())
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as total, COALESCE(SUM(cash_refund), 0) as cash')
            ->first();

        return view('purchase_returns.index', compact('returns', 'totals'));
    }

    public function create(Purchase $purchase)
    {
        $this->authorize('view', $purchase);

        $purchase->load(['items.product:id,name', 'supplier:id,name,balance']);
        $warehouseId = Stock::warehouseFor((int) $purchase->branch_id);

        // ما بقي فعلاً من كل دفعة (لا يُرجَّع ما بِيع)
        $batchQty = ProductBatch::withoutGlobalScopes()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $purchase->items->pluck('product_id'))
            ->get(['product_id', 'batch_number', 'quantity'])
            ->mapWithKeys(fn ($b) => [$b->product_id . '|' . $b->batch_number => (int) $b->quantity]);

        $lines = $purchase->items->map(function ($item) use ($batchQty, $purchase) {
            $available = $item->batch_number
                ? (int) ($batchQty[$item->product_id . '|' . $item->batch_number] ?? 0)
                : Stock::quantity((int) $item->product_id, (int) $purchase->branch_id);

            return [
                'item'      => $item,
                'max'       => max(0, min($item->returnableQuantity(), $available)),
                'available' => $available,
            ];
        });

        $registers = CashRegister::where('status', 'active')->orderBy('name')->get(['id', 'name', 'current_balance']);

        return view('purchase_returns.create', compact('purchase', 'lines', 'registers'));
    }

    public function store(Request $request, Purchase $purchase)
    {
        $this->authorize('view', $purchase);

        $validated = $request->validate([
            'quantities'       => 'required|array',
            'quantities.*'     => 'nullable|integer|min:0',
            'excess_mode'      => 'nullable|in:cash,credit',
            'cash_register_id' => 'nullable|integer|exists:cash_registers,id,deleted_at,NULL',
            'reason'           => 'nullable|string|max:500',
        ]);

        $return = PurchaseReturnService::create(
            $purchase,
            $validated['quantities'],
            $validated['excess_mode'] ?? 'credit',
            $validated['cash_register_id'] ?? null,
            $validated['reason'] ?? null,
            $request->user()
        );

        $msg = 'تم تسجيل المرتجع ' . $return->return_number . ' بقيمة ' . number_format((float) $return->total, 2) . ' ₪';
        if ((float) $return->debt_reduction > 0) {
            $msg .= '، وخُصم من المستحق للمورد ' . number_format((float) $return->debt_reduction, 2) . ' ₪';
        }
        if ((float) $return->cash_refund > 0) {
            $msg .= '، واسترددنا نقداً ' . number_format((float) $return->cash_refund, 2) . ' ₪';
        }
        if ((float) $return->supplier_credit > 0) {
            $msg .= '، وبقي لنا عند المورد ' . number_format((float) $return->supplier_credit, 2) . ' ₪';
        }

        return redirect()->route('purchase-returns.show', $return)->with('success', $msg . '.');
    }

    public function show(PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->load(['purchase', 'supplier', 'branch', 'user', 'cashRegister', 'items.product:id,name', 'items.batch:id,batch_number,expiry_date']);

        return view('purchase_returns.show', ['return' => $purchaseReturn]);
    }
}
