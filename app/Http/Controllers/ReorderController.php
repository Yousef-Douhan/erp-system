<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * اقتراح طلبية شراء: الأصناف التي وصلت للحد الأدنى أو ستنفد قريباً حسب سرعة بيعها،
 * مع الكمية المقترحة وآخر مورد وآخر سعر شراء، مجمّعة حسب المورد للطباعة أو الإرسال على واتساب.
 * لا يغيّر أي شيء في المخزون: الاستلام الفعلي يكون بفاتورة شراء.
 */
class ReorderController extends Controller
{
    public function index(Request $request)
    {
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $branchId = Tenancy::branchId() ?? ($request->integer('branch_id') ?: ($branches->first()->id ?? null));
        $cover = max(7, min(90, (int) $request->input('cover', 30)));   // تكفي كم يوماً بعد الاستلام

        $since = now()->subDays(30);

        // الكمية المباعة (صافي المرتجعات) في آخر 30 يوماً في الفرع
        $sold = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNull('sales.deleted_at')->whereNull('sale_items.deleted_at')
            ->where('sales.branch_id', $branchId)
            ->where('sales.created_at', '>=', $since)
            ->groupBy('sale_items.product_id')
            ->selectRaw('sale_items.product_id, SUM((sale_items.quantity - COALESCE(sale_items.returned_quantity, 0)) * COALESCE(sale_items.unit_factor, 1)) AS qty')
            ->pluck('qty', 'product_id');

        $products = Product::query()
            ->where('products.is_active', true)
            ->leftJoin('branch_stocks as bs', fn ($j) => $j->on('bs.product_id', '=', 'products.id')->where('bs.branch_id', $branchId))
            ->select('products.id', 'products.name', 'products.barcode', 'products.alert_quantity', 'products.purchase_price')
            ->selectRaw('COALESCE(bs.quantity, 0) AS branch_qty')
            ->get();

        // آخر مورد وآخر سعر شراء لكل صنف
        $last = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->whereNull('purchases.deleted_at')->whereNull('purchase_items.deleted_at')
            ->whereIn('purchase_items.product_id', $products->pluck('id'))
            ->orderByDesc('purchases.created_at')->orderByDesc('purchase_items.id')
            ->get(['purchase_items.product_id', 'purchase_items.unit_cost', 'suppliers.id as supplier_id', 'suppliers.name as supplier_name'])
            ->unique('product_id')->keyBy('product_id');

        $lines = $products->map(function ($p) use ($sold, $cover, $last) {
            $daily = round((float) ($sold[$p->id] ?? 0) / 30, 2);
            $stock = (int) $p->branch_qty;   // ليس ->stock: الموديل فيه accessor بهذا الاسم يرجّع stock_quantity
            $alert = (int) $p->alert_quantity;
            $daysLeft = $daily > 0 ? (int) floor($stock / $daily) : null;

            $needed = $stock <= $alert || ($daysLeft !== null && $daysLeft < 7);
            if (!$needed) {
                return null;
            }

            $target = max($alert * 2, (int) ceil($daily * $cover), 1);
            $suggest = max(0, $target - $stock);
            if ($suggest <= 0) {
                return null;
            }

            $l = $last[$p->id] ?? null;
            $cost = (float) ($l->unit_cost ?? $p->purchase_price);

            return [
                'id' => $p->id, 'name' => $p->name, 'barcode' => $p->barcode,
                'stock' => $stock, 'alert' => $alert, 'daily' => $daily, 'days_left' => $daysLeft,
                'suggest' => $suggest, 'cost' => $cost, 'value' => round($suggest * $cost, 2),
                'supplier_id' => $l->supplier_id ?? null, 'supplier' => $l->supplier_name ?? 'بدون مورد سابق',
            ];
        })->filter()->sortBy([['days_left', 'asc'], ['name', 'asc']])->values();

        $bySupplier = $lines->groupBy('supplier')->sortKeys();
        $totalValue = round($lines->sum('value'), 2);
        $branchName = $branches->firstWhere('id', $branchId)->name ?? '';

        return view('reorder.index', compact('branches', 'branchId', 'branchName', 'cover', 'lines', 'bySupplier', 'totalValue'));
    }
}
