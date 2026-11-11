<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\SupplierAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseController extends Controller
{
    /**
     * إنشاء فاتورة شراء كاملة عبر الـ API مع إنشاء الدفعات وزيادة المخزون
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'organization_id'       => 'nullable|exists:organizations,id,deleted_at,NULL',
            'branch_id'             => 'required|exists:branches,id,deleted_at,NULL',
            'supplier_id'           => 'required|exists:suppliers,id,deleted_at,NULL',
            'warehouse_id'          => 'nullable|exists:warehouses,id,deleted_at,NULL',
            'invoice_number'        => 'required|string|max:255|unique:purchases,invoice_number',
            'paid_amount'           => 'nullable|numeric|min:0',
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'required|exists:products,id,deleted_at,NULL',
            'items.*.quantity'      => 'required|integer|min:1',
            'items.*.cost_price'    => 'required|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',
            'items.*.batch_number'  => 'required|string|max:100',
            'items.*.expiry_date'   => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        try {
            $purchase = DB::transaction(function () use ($data, $request) {
                $total = collect($data['items'])->sum(fn ($i) => $i['quantity'] * $i['cost_price']);
                $paid = min((float) ($data['paid_amount'] ?? 0), $total);

                $purchase = Purchase::create([
                    'organization_id' => $data['organization_id'] ?? $request->user()->organization_id ?? 1,
                    'branch_id'       => $data['branch_id'],
                    'supplier_id'     => $data['supplier_id'],
                    'employee_id'     => $request->user()->id,
                    'invoice_number'  => $data['invoice_number'],
                    'total'           => $total,
                    'paid_amount'     => $paid,
                    'status'          => $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                ]);

                foreach ($data['items'] as $item) {
                    PurchaseItem::create([
                        'purchase_id'  => $purchase->id,
                        'product_id'   => $item['product_id'],
                        'batch_number' => $item['batch_number'],
                        'expiry_date'  => $item['expiry_date'] ?? null,
                        'quantity'     => $item['quantity'],
                        'unit_cost'    => $item['cost_price'],
                        'total'        => $item['quantity'] * $item['cost_price'],
                    ]);

                    ProductBatch::create([
                        'product_id'    => $item['product_id'],
                        'warehouse_id'  => $data['warehouse_id'] ?? null,
                        'batch_number'  => $item['batch_number'],
                        'expiry_date'   => $item['expiry_date'] ?? null,
                        'cost_price'    => $item['cost_price'],
                        'selling_price' => $item['selling_price'] ?? 0,
                        'quantity'      => $item['quantity'],
                    ]);

                    $product = Product::whereKey($item['product_id'])->lockForUpdate()->first();
                    $product->increment('stock_quantity', $item['quantity']);
                    $product->update(['purchase_price' => $item['cost_price']]);
                }

                // المتبقي يصبح ديناً للمورد علينا
                $remaining = $total - $paid;
                if ($remaining > 0) {
                    $supplier = Supplier::whereKey($data['supplier_id'])->lockForUpdate()->first();
                    $supplier->balance = round((float) $supplier->balance + $remaining, 2);
                    $supplier->save();

                    SupplierAccount::create([
                        'supplier_id'      => $supplier->id,
                        'transaction_type' => 'purchase_invoice',
                        'debit'            => 0,
                        'credit'           => $remaining,
                        'balance'          => $supplier->balance,
                    ]);
                }

                return $purchase;
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء إتمام عملية الشراء.',
            ], 500);
        }

        return response()->json([
            'status'         => 'success',
            'message'        => 'تمت عملية الشراء بنجاح وتحديث المخزون وإضافة الدفعات!',
            'invoice_number' => $purchase->invoice_number,
            'total'          => $purchase->total,
        ], 201);
    }
}
