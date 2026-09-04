<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\ProductBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseController extends Controller
{
    public function store(Request $request)
    {
        // التحقق من صحة البيانات الواردة
        $validator = Validator::make($request->all(), [
            'organization_id' => 'required',
            'branch_id' => 'required',
            'supplier_id' => 'required',
            'employee_id' => 'required',
            'invoice_number' => 'required|string',
            'total' => 'required|numeric',
            'items' => 'required|array',
            'items.*.product_id' => 'required',
            'items.*.quantity' => 'required|integer',
            'items.*.cost_price' => 'required|numeric', // تأكدنا أنه يستقبل cost_price
            'items.*.selling_price' => 'required|numeric',
            'items.*.batch_number' => 'required|string',
            'items.*.expiry_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // 1. إنشاء فاتورة المشتريات الرئيسية
            $purchase = Purchase::create([
                'organization_id' => $request->organization_id,
                'branch_id' => $request->branch_id,
                'supplier_id' => $request->supplier_id,
                'employee_id' => $request->employee_id,
                'invoice_number' => $request->invoice_number,
                'total' => $request->total,
                'status' => 'received',
            ]);

            // 2. إدخال عناصر الشراء وتوليد دفعات مخزونية جديدة (Batches)
            foreach ($request->items as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'cost_price' => $item['cost_price'],
                    'total' => $item['quantity'] * $item['cost_price'],
                ]);

                // إضافة دفعة جديدة للمخزون لتصبح متاحة للبيع لاحقاً
                ProductBatch::create([
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $request->branch_id,
                    'batch_number' => $item['batch_number'],
                    'expiry_date' => $item['expiry_date'],
                    'cost_price' => $item['cost_price'],
                    'selling_price' => $item['selling_price'],
                    'quantity' => $item['quantity'], // الكمية الواردة للمخزون
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'تمت عملية الشراء بنجاح وتحديث المخزون وإضافة الدفعات!',
                'invoice_number' => $purchase->invoice_number,
                'total' => $purchase->total
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء إتمام عملية الشراء: ' . $e->getMessage()
            ], 500);
        }
    }
}
