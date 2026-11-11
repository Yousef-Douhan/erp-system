<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryTransactionController extends Controller
{
    public function index()
    {
        $transactions = InventoryTransaction::with(['warehouse', 'product'])->latest()->paginate(15);
        $warehouses = Warehouse::all();
        $products = Product::orderBy('name')->get(['id', 'name', 'stock_quantity']);

        return view('inventory_transactions.index', compact('transactions', 'warehouses', 'products'));
    }

    /**
     * صفحة توريد بضاعة للمخزون (Stock In)
     */
    public function createStockIn()
    {
        $products = Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'stock_quantity']);
        \App\Support\Stock::overlay($products);
        $warehouses = Warehouse::all();

        return view('inventory.transactions.stock-in', compact('products', 'warehouses'));
    }

    /**
     * حفظ توريد بضاعة: ينشئ حركة وارد لكل صنف ويزيد المخزون تلقائياً
     */
    public function storeStockIn(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id'           => 'nullable|exists:warehouses,id,deleted_at,NULL',
            'reference_number'       => 'nullable|string|max:255',
            'notes'                  => 'nullable|string|max:1000',
            'items'                  => 'required|array|min:1',
            'items.*.product_id'     => 'required|exists:products,id,deleted_at,NULL',
            'items.*.quantity'       => 'required|integer|min:1',
            'items.*.purchase_price' => 'nullable|numeric|min:0',
        ]);

        \App\Support\PriceGuard::check(collect($validated['items'])
            ->mapWithKeys(fn ($item, $i) => ["items.{$i}.purchase_price" => [$item['product_id'], $item['purchase_price'] ?? null]])
            ->all());

        DB::transaction(function () use ($validated) {
            // المستودع يجب أن يكون من مستودعات المستخدم، وإلا مستودع فرعه
            $warehouseId = !empty($validated['warehouse_id'])
                ? Warehouse::findOrFail($validated['warehouse_id'])->id
                : \App\Support\Stock::warehouseFor(\App\Support\Tenancy::defaultBranchId());

            foreach ($validated['items'] as $item) {
                InventoryTransaction::create([
                    'warehouse_id' => $warehouseId,
                    'product_id'   => $item['product_id'],
                    'type'         => 'stock_in',
                    'qty_in'       => $item['quantity'],
                    'qty_out'      => 0,
                ]);

                if (isset($item['purchase_price']) && $item['purchase_price'] !== '') {
                    Product::whereKey($item['product_id'])->update(['purchase_price' => $item['purchase_price']]);
                }
            }
        });

        return redirect()->route('inventory.transactions.index')->with('success', 'تم توريد البضاعة وتحديث المخزون بنجاح.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id,deleted_at,NULL',
            'product_id'   => 'required|exists:products,id,deleted_at,NULL',
            'batch_id'     => 'nullable|exists:product_batches,id',
            'type'         => 'required|string|max:255',
            'qty_in'       => 'required|numeric|min:0',
            'qty_out'      => 'required|numeric|min:0',
        ]);

        Warehouse::findOrFail($validated['warehouse_id']);
        Product::findOrFail($validated['product_id']);

        $error = $this->checkAvailableStock($validated);
        if ($error) {
            return back()->withErrors($error)->withInput();
        }

        DB::transaction(fn () => InventoryTransaction::create($validated));

        return redirect()->route('inventory-transactions.index')->with('success', 'تم تسجيل الحركة المخزنية بنجاح.');
    }

    public function update(Request $request, InventoryTransaction $inventoryTransaction)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id,deleted_at,NULL',
            'product_id'   => 'required|exists:products,id,deleted_at,NULL',
            'batch_id'     => 'nullable|exists:product_batches,id',
            'type'         => 'required|string|max:255',
            'qty_in'       => 'required|numeric|min:0',
            'qty_out'      => 'required|numeric|min:0',
        ]);

        Warehouse::findOrFail($validated['warehouse_id']);
        Product::findOrFail($validated['product_id']);

        DB::transaction(fn () => $inventoryTransaction->update($validated));

        return redirect()->route('inventory-transactions.index')->with('success', 'تم تحديث الحركة المخزنية بنجاح.');
    }

    public function destroy(InventoryTransaction $inventoryTransaction)
    {
        DB::transaction(fn () => $inventoryTransaction->delete());

        return redirect()->route('inventory-transactions.index')->with('success', 'تم حذف الحركة المخزنية بنجاح.');
    }

    /**
     * منع إخراج كمية أكبر من المتوفر
     */
    private function checkAvailableStock(array $data): ?string
    {
        $product = Product::find($data['product_id']);
        $net = (float) $data['qty_in'] - (float) $data['qty_out'];
        $branchId = \App\Support\Stock::branchOfWarehouse((int) $data['warehouse_id']);
        $available = $product ? \App\Support\Stock::quantity($product->id, $branchId) : 0;

        if ($product && $available + $net < 0) {
            return "الكمية المطلوب إخراجها أكبر من المتوفر للمنتج ({$product->name}) في هذا الفرع. المتوفر: {$available}";
        }

        return null;
    }
}
