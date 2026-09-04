<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Warehouse;
use App\Models\Product;
use Illuminate\Http\Request;

class InventoryTransactionController extends Controller
{
    public function index()
    {
        $transactions = InventoryTransaction::with(['warehouse', 'product'])->latest()->paginate(15);
        $warehouses = Warehouse::all();
        $products = Product::all();

        return view('inventory_transactions.index', compact('transactions', 'warehouses', 'products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id'  => 'required|exists:warehouses,id',
            'product_id'    => 'required|exists:products,id',
            'batch_id'      => 'nullable|integer',
            'type'          => 'required|string|max:255',
            'qty_in'        => 'required|numeric|min:0',
            'qty_out'       => 'required|numeric|min:0',
            'balance_after' => 'required|numeric',
        ]);

        InventoryTransaction::create($validated);

        return redirect()->route('inventory-transactions.index')->with('success', 'تم تسجيل الحركة المخزنية بنجاح.');
    }

    public function update(Request $request, InventoryTransaction $inventoryTransaction)
    {
        $validated = $request->validate([
            'warehouse_id'  => 'required|exists:warehouses,id',
            'product_id'    => 'required|exists:products,id',
            'batch_id'      => 'nullable|integer',
            'type'          => 'required|string|max:255',
            'qty_in'        => 'required|numeric|min:0',
            'qty_out'       => 'required|numeric|min:0',
            'balance_after' => 'required|numeric',
        ]);

        $inventoryTransaction->update($validated);

        return redirect()->route('inventory-transactions.index')->with('success', 'تم تحديث الحركة المخزنية بنجاح.');
    }

    public function destroy(InventoryTransaction $inventoryTransaction)
    {
        $inventoryTransaction->delete();

        return redirect()->route('inventory-transactions.index')->with('success', 'تم حذف الحركة المخزنية بنجاح.');
    }
}