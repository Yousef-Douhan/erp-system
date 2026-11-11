<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockCount;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * الجرد: لقطة من أرصدة النظام ← إدخال الكميات الفعلية ← ترحيل الفروقات كحركات تسوية.
 */
class StockCountController extends Controller
{
    public function index(Request $request)
    {
        $counts = StockCount::visibleTo($request->user())
            ->with(['user:id,username', 'poster:id,username', 'branch:id,name'])
            ->withCount([
                'items',
                'items as counted_count' => fn ($q) => $q->whereNotNull('counted_qty'),
                'items as diff_count' => fn ($q) => $q->whereNotNull('difference')->where('difference', '!=', 0),
            ])
            ->latest()
            ->paginate(20);

        $categories = Category::orderBy('name')->get(['id', 'name']);
        $branches = \App\Models\Branch::orderBy('name')->get(['id', 'name']);

        return view('stock_counts.index', compact('counts', 'categories', 'branches'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id,deleted_at,NULL',
            'branch_id'   => 'nullable|integer',
            'notes'       => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        // الجرد لفرع واحد: فرع المستخدم، أو الفرع الذي اختاره صاحب الصيدلية
        $branch = \App\Models\Branch::findOrFail(\App\Support\Tenancy::branchId() ?? ($validated['branch_id'] ?? null) ?? \App\Support\Tenancy::defaultBranchId());

        $count = DB::transaction(function () use ($validated, $user, $branch) {
            $products = Product::visibleTo($user)
                ->where('is_active', true)
                ->when($validated['category_id'] ?? null, fn ($q, $cat) => $q->where('category_id', $cat))
                ->orderBy('name')
                ->get(['id', 'stock_quantity', 'purchase_price']);
            \App\Support\Stock::overlay($products, $branch->id);

            if ($products->isEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['category_id' => 'لا توجد أصناف نشطة في هذا النطاق.']);
            }

            $count = StockCount::create([
                'organization_id' => $branch->organization_id,
                'branch_id'       => $branch->id,
                'user_id'         => $user->id,
                'count_number'    => 'TMP-' . uniqid('', true),
                'scope'           => $branch->name . '، ' . (!empty($validated['category_id']) ? 'تصنيف: ' . Category::whereKey($validated['category_id'])->value('name') : 'كل الأصناف'),
                'status'          => 'draft',
                'notes'           => $validated['notes'] ?? null,
            ]);
            $count->update(['count_number' => 'CNT-' . date('Ymd') . '-' . str_pad($count->id, 4, '0', STR_PAD_LEFT)]);

            $now = now();
            $count->items()->insert($products->map(fn ($p) => [
                'stock_count_id' => $count->id,
                'product_id'     => $p->id,
                'system_qty'     => (int) $p->stock_quantity,
                'unit_cost'      => (float) $p->purchase_price,
                'created_at'     => $now,
                'updated_at'     => $now,
            ])->all());

            return $count;
        });

        return redirect()->route('stock-counts.show', $count)->with('success', 'تم إنشاء ورقة الجرد. أدخل الكميات الموجودة فعلاً على الرف ثم احفظ.');
    }

    public function show(Request $request, StockCount $stockCount)
    {
        $this->ensureVisible($request, $stockCount);

        $stockCount->load(['user:id,username', 'poster:id,username']);
        $items = $stockCount->items()
            ->with('product:id,name,barcode,stock_quantity')
            ->join('products', 'products.id', '=', 'stock_count_items.product_id')
            ->orderBy('products.name')
            ->select('stock_count_items.*')
            ->get();
        \App\Support\Stock::overlay($items->pluck('product')->filter(), $stockCount->branch_id);

        // في المسودة: الفرق يُحسب على الرصيد الحالي (قد يتغير أثناء الجرد بسبب البيع)
        $rows = $items->map(function ($item) use ($stockCount) {
            $current = $stockCount->isDraft() ? (int) ($item->product->stock_quantity ?? 0) : (int) $item->system_qty;
            $diff = $item->counted_qty === null ? null : ($stockCount->isDraft() ? (int) $item->counted_qty - $current : (int) $item->difference);

            return ['item' => $item, 'current' => $current, 'diff' => $diff, 'value' => $diff === null ? 0 : $diff * (float) $item->unit_cost];
        });

        $stats = [
            'total'    => $rows->count(),
            'counted'  => $rows->whereNotNull('diff')->count(),
            'shortage' => $rows->filter(fn ($r) => ($r['diff'] ?? 0) < 0)->count(),
            'surplus'  => $rows->filter(fn ($r) => ($r['diff'] ?? 0) > 0)->count(),
            'value'    => round($rows->sum('value'), 2),
        ];

        return view('stock_counts.show', compact('stockCount', 'rows', 'stats'));
    }

    /**
     * حفظ الكميات المعدودة (المسودة)
     */
    public function update(Request $request, StockCount $stockCount)
    {
        $this->ensureVisible($request, $stockCount);
        abort_unless($stockCount->isDraft(), 422, 'تم ترحيل هذا الجرد ولا يمكن تعديله.');

        $validated = $request->validate([
            'counted'   => 'array',
            'counted.*' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($stockCount, $validated) {
            $items = $stockCount->items()->get()->keyBy('id');
            foreach ($validated['counted'] ?? [] as $itemId => $qty) {
                if ($item = $items[(int) $itemId] ?? null) {
                    $item->update(['counted_qty' => ($qty === null || $qty === '') ? null : (int) $qty]);
                }
            }
        });

        if ($request->input('action') === 'post') {
            return $this->post($request, $stockCount);
        }

        return back()->with('success', 'تم حفظ الكميات. يمكنك المتابعة لاحقاً، ثم ترحيل الجرد عند الانتهاء.');
    }

    /**
     * ترحيل الجرد: كل فرق يصبح حركة "تسوية جرد" تضبط رصيد المنتج والدفعات
     */
    public function post(Request $request, StockCount $stockCount)
    {
        $this->ensureVisible($request, $stockCount);
        abort_unless($request->user()->can('edit_products'), 403);

        $adjusted = DB::transaction(function () use ($request, $stockCount) {
            $stockCount = StockCount::whereKey($stockCount->id)->lockForUpdate()->firstOrFail();
            if (!$stockCount->isDraft()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['count' => 'تم ترحيل هذا الجرد مسبقاً.']);
            }

            $adjusted = 0;
            foreach ($stockCount->items()->whereNotNull('counted_qty')->get() as $item) {
                $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
                if (!$product) {
                    continue;
                }

                $current = \App\Support\Stock::quantity($product->id, $stockCount->branch_id);
                $diff = (int) $item->counted_qty - $current;
                $item->update(['system_qty' => $current, 'difference' => $diff]);

                if ($diff === 0) {
                    continue;
                }

                // العجز يُخصم من الدفعات فقط بقدر ما تزيد كمياتها عن المعدود فعلاً
                if ($diff < 0) {
                    $warehouses = \App\Support\Stock::warehouseIdsOfBranch((int) $stockCount->branch_id) ?: [0];
                    $batchSum = (int) ProductBatch::withoutGlobalScopes()->where('product_id', $product->id)->whereIn('warehouse_id', $warehouses)->where('quantity', '>', 0)->sum('quantity');
                    $this->reduceBatches($product->id, min(abs($diff), max(0, $batchSum - (int) $item->counted_qty)), $warehouses);
                }

                InventoryTransaction::create([
                    'warehouse_id'   => \App\Support\Stock::warehouseFor((int) $stockCount->branch_id),
                    'product_id'     => $product->id,
                    'type'           => 'adjustment',
                    'reference_type' => 'stock_count',
                    'reference_id'   => $stockCount->id,
                    'qty_in'         => max(0, $diff),
                    'qty_out'        => max(0, -$diff),
                ]);
                $adjusted++;
            }

            $stockCount->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $request->user()->id]);

            return $adjusted;
        });

        return redirect()->route('stock-counts.show', $stockCount)
            ->with('success', $adjusted ? "تم ترحيل الجرد وتسوية {$adjusted} صنف." : 'تم ترحيل الجرد. لا توجد فروقات.');
    }

    public function destroy(Request $request, StockCount $stockCount)
    {
        $this->ensureVisible($request, $stockCount);
        abort_unless($stockCount->isDraft(), 422, 'لا يمكن حذف جرد مُرحَّل.');

        $stockCount->delete();

        return redirect()->route('stock-counts.index')->with('success', 'تم حذف مسودة الجرد.');
    }

    /**
     * العجز يُخصم من الدفعات: المنتهية أولاً ثم الأقرب انتهاءً، حتى لا تبقى كميات في دفعات غير موجودة فعلاً
     */
    private function reduceBatches(int $productId, int $qty, array $warehouses): void
    {
        $batches = ProductBatch::withoutGlobalScopes()->where('product_id', $productId)
            ->whereIn('warehouse_id', $warehouses)
            ->where('quantity', '>', 0)
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($qty <= 0) {
                break;
            }
            $take = min((int) $batch->quantity, $qty);
            $batch->decrement('quantity', $take);
            $qty -= $take;
        }
    }

    private function ensureVisible(Request $request, StockCount $count): void
    {
        abort_unless(StockCount::visibleTo($request->user())->whereKey($count->id)->exists(), 403);
    }
}
