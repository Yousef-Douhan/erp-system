<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;

/**
 * سجل الحركات المخزنية (للعرض فقط).
 * كل حركة تُنشأ تلقائياً من مصدرها: البيع، المرتجع، فاتورة الشراء، النقل، الجرد، الاستيراد.
 * لا إضافة ولا تعديل ولا حذف يدوي، والتسوية تكون من «الجرد» حتى يبقى تاريخ المخزون صحيحاً.
 */
class InventoryTransactionController extends Controller
{
    public const TYPES = [
        'sale'              => 'بيع',
        'sale_return'       => 'مرتجع مبيعات',
        'sale_reversed'     => 'إلغاء فاتورة بيع',
        'purchase'          => 'فاتورة شراء',
        'purchase_reversed' => 'إلغاء فاتورة شراء',
        'purchase_return'   => 'مرتجع للمورد',
        'disposal'          => 'إتلاف',
        'opening_stock'     => 'رصيد افتتاحي',
        'adjustment'        => 'تسوية جرد',
        'transfer_in'       => 'نقل وارد',
        'transfer_out'      => 'نقل صادر',
    ];

    public function index(Request $request)
    {
        $from = $request->date('from');
        $to = $request->date('to');

        $transactions = InventoryTransaction::with(['warehouse:id,name', 'product:id,name'])
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $products = Product::orderBy('name')->get(['id', 'name']);
        $types = self::TYPES;

        return view('inventory_transactions.index', compact('transactions', 'warehouses', 'products', 'types'));
    }
}
