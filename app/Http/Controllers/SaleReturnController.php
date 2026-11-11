<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\ReturnService;
use App\Services\SaleService;
use Illuminate\Http\Request;

class SaleReturnController extends Controller
{
    public function index(Request $request)
    {
        $returns = SaleReturn::visibleTo($request->user())
            ->with(['sale:id,invoice_number', 'customer:id,name', 'user:id,username'])
            ->withCount('items')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim($request->q);
                $q->where(fn ($w) => $w->where('return_number', 'like', "%{$term}%")
                    ->orWhereHas('sale', fn ($s) => $s->where('invoice_number', 'like', "%{$term}%")));
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $totals = SaleReturn::visibleTo($request->user())
            ->whereDate('created_at', '>=', today()->startOfMonth())
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as total, COALESCE(SUM(refund_amount), 0) as refund')
            ->first();

        return view('returns.index', compact('returns', 'totals'));
    }

    /**
     * نموذج المرتجع لفاتورة محددة
     */
    public function create(Request $request, Sale $sale)
    {
        $this->authorize('view', $sale);

        $sale->load(['items.product:id,name', 'customer:id,name,balance', 'returns' => fn ($q) => $q->latest()]);
        $itemsSum = (float) $sale->items->sum('total');
        $discountRatio = $itemsSum > 0 ? min(1, (float) $sale->discount / $itemsSum) : 0;

        $lines = $sale->items->map(fn ($item) => [
            'item'       => $item,
            'returnable' => $item->returnableQuantity(),
            'unit_price' => ReturnService::unitNetPrice($item, $discountRatio),
        ]);

        $remaining = SaleService::remaining($sale);
        $registers = CashRegister::where('status', 'active')->orderBy('name')->get(['id', 'name', 'current_balance']);

        return view('returns.create', compact('sale', 'lines', 'remaining', 'registers'));
    }

    public function store(Request $request, Sale $sale)
    {
        $this->authorize('view', $sale);

        $validated = $request->validate([
            'quantities'       => 'required|array',
            'quantities.*'     => 'nullable|numeric|min:0',
            'restock'          => 'nullable|boolean',
            'reason'           => 'nullable|string|max:500',
            'cash_register_id' => 'nullable|exists:cash_registers,id,deleted_at,NULL',
        ]);

        $return = ReturnService::create(
            $sale,
            $validated['quantities'],
            $request->boolean('restock', true),
            $validated['reason'] ?? null,
            $request->user(),
            $validated['cash_register_id'] ?? null
        );

        $msg = 'تم تسجيل المرتجع ' . $return->return_number . ' بقيمة ' . number_format((float) $return->total, 2) . ' ₪';
        if ((float) $return->refund_amount > 0) {
            $msg .= '، ويُرد للزبون ' . number_format((float) $return->refund_amount, 2) . ' ₪';
        }
        if ((float) $return->debt_reduction > 0) {
            $msg .= '، وخُفّض دين العميل ' . number_format((float) $return->debt_reduction, 2) . ' ₪';
        }

        return redirect()->route('returns.show', $return)->with('success', $msg . '.');
    }

    public function show(Request $request, SaleReturn $return)
    {
        abort_unless(SaleReturn::visibleTo($request->user())->whereKey($return->id)->exists(), 403);

        $return->load(['sale', 'customer', 'user', 'cashRegister', 'items.product:id,name', 'items.saleItem:id,unit_name']);

        return view('returns.show', compact('return'));
    }
}
