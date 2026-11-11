<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ProductBatch;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::with('branch:id,name')
            ->withCount(['batches as batches_count' => fn ($q) => $q->where('quantity', '>', 0)])
            ->withSum(['batches as stock_qty' => fn ($q) => $q->where('quantity', '>', 0)], 'quantity')
            ->orderBy('name')
            ->get();

        $unassigned = (int) ProductBatch::whereNull('warehouse_id')->where('quantity', '>', 0)->sum('quantity');
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        return view('warehouses.index', compact('warehouses', 'branches', 'unassigned'));
    }

    public function store(Request $request)
    {
        Warehouse::create($this->validated($request));

        return back()->with('success', 'تم إضافة المستودع.');
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $warehouse->update($this->validated($request, $warehouse));

        return back()->with('success', 'تم تحديث المستودع.');
    }

    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->batches()->where('quantity', '>', 0)->exists()) {
            return back()->with('error', 'لا يمكن حذف مستودع فيه كميات. انقلها لمستودع آخر أولاً.');
        }

        try {
            $warehouse->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->with('error', 'لا يمكن حذف هذا المستودع لأنه مرتبط بحركات مخزنية سابقة.');
        }

        return back()->with('success', 'تم حذف المستودع.');
    }

    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        return $request->validate([
            // الاسم فريد داخل الصيدلية
            'name'      => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('warehouses', 'name')
                ->where(fn ($q) => \App\Support\Tenancy::active() ? $q->whereIn('branch_id', \App\Support\Tenancy::orgBranchIds() ?: [0]) : $q)
                ->ignore($warehouse?->id)],
            'branch_id' => 'nullable|exists:branches,id,deleted_at,NULL',
        ]);
    }
}
