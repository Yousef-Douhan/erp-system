<?php

namespace App\Http\Controllers;

use App\Models\ProductBatch;
use App\Models\Warehouse;
use Illuminate\Http\Request;

/**
 * الدفعات (التشغيلات وتواريخ الصلاحية) — للعرض والمتابعة.
 *
 * الكميات لا تُعدَّل من هنا أبداً: تدخل الدفعة بفاتورة شراء، وتخرج ببيع أو نقل أو مرتجع للمورد أو إتلاف،
 * وكل ذلك يكتب حركة مخزنية ويحدّث رصيد الفرع. المسموح هنا فقط تصحيح تاريخ الإنتاج/الانتهاء.
 */
class ProductBatchController extends Controller
{
    public function indexAll(Request $request)
    {
        $status = $request->query('status', 'active');
        $soon = today()->addDays(90);

        $query = ProductBatch::with(['product:id,name,purchase_price', 'warehouse:id,name'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim($request->q);
                $q->where(fn ($w) => $w->where('batch_number', 'like', "%{$term}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$term}%")));
            })
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($status !== 'all', fn ($q) => $q->where('quantity', '>', 0))
            ->when($status === 'expired', fn ($q) => $q->whereDate('expiry_date', '<', today()))
            ->when($status === 'expiring', fn ($q) => $q->whereDate('expiry_date', '>=', today())->whereDate('expiry_date', '<=', $soon))
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->orderBy('id');

        $batches = $query->paginate(25)->withQueryString();

        $counts = [
            'expired'  => ProductBatch::where('quantity', '>', 0)->whereDate('expiry_date', '<', today())->count(),
            'expiring' => ProductBatch::where('quantity', '>', 0)->whereDate('expiry_date', '>=', today())->whereDate('expiry_date', '<=', $soon)->count(),
            'expired_value' => (float) ProductBatch::where('product_batches.quantity', '>', 0)->whereDate('product_batches.expiry_date', '<', today())
                ->join('products', 'products.id', '=', 'product_batches.product_id')
                ->selectRaw('COALESCE(SUM(product_batches.quantity * COALESCE(NULLIF(product_batches.cost_price, 0), products.purchase_price)), 0) AS v')
                ->value('v'),
        ];

        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $reasons = \App\Models\StockDisposal::REASONS;

        return view('batches.index', compact('batches', 'warehouses', 'counts', 'status', 'reasons'));
    }

    /**
     * تصحيح تاريخ الإنتاج/الانتهاء فقط (رقم الدفعة يربطها بفاتورة الشراء فلا يتغير)
     */
    public function updateAll(Request $request, ProductBatch $batch)
    {
        $validated = $request->validate([
            'manufacturing_date' => 'nullable|date',
            'expiry_date'        => 'nullable|date|after_or_equal:manufacturing_date',
        ]);

        $batch->update($validated);

        return back()->with('success', 'تم تحديث تواريخ الدفعة ' . $batch->batch_number . '.');
    }
}
