<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Unit;
use App\Models\Notification; // تأكد من استدعاء موديل الإشعارات
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $products = Product::with(['category', 'brand', 'unit'])
            ->when($search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10);

        $categories = Category::all();
        $brands = Brand::all();
        $units = Unit::all();

        return view('products.index', compact('products', 'categories', 'brands', 'units'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'unit_id' => 'nullable|exists:units,id',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'alert_quantity' => 'required|integer|min:0',
            'expiry_date' => 'nullable|date',
            'description' => 'nullable|string|max:1000',
            'track_expiry' => 'sometimes|boolean',
            'is_controlled' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $product = Product::create([
            'organization_id' => 1,
            'category_id' => $validated['category_id'] ?? null,
            'brand_id' => $validated['brand_id'] ?? null,
            'unit_id' => $validated['unit_id'] ?? null,
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? null,
            'barcode' => $validated['barcode'] ?? null,
            'purchase_price' => $validated['purchase_price'],
            'selling_price' => $validated['selling_price'],
            'stock_quantity' => $validated['stock_quantity'],
            'alert_quantity' => $validated['alert_quantity'],
            'expiry_date' => $validated['expiry_date'] ?? null,
            'description' => $validated['description'] ?? null,
            'track_expiry' => $request->has('track_expiry'),
            'is_controlled' => $request->has('is_controlled'),
            'is_active' => $request->has('is_active'),
        ]);

        // فحص المخزون عند الإضافة
        $this->checkStockAlert($product);

        return redirect()->route('products.index')->with('success', 'تم إضافة المنتج بنجاح.');
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100|unique:products,barcode,' . $product->id,
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'unit_id' => 'nullable|exists:units,id',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'alert_quantity' => 'required|integer|min:0',
            'expiry_date' => 'nullable|date',
            'description' => 'nullable|string|max:1000',
            'track_expiry' => 'sometimes|boolean',
            'is_controlled' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $product->update([
            'category_id' => $validated['category_id'] ?? null,
            'brand_id' => $validated['brand_id'] ?? null,
            'unit_id' => $validated['unit_id'] ?? null,
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? null,
            'barcode' => $validated['barcode'] ?? null,
            'purchase_price' => $validated['purchase_price'],
            'selling_price' => $validated['selling_price'],
            'stock_quantity' => $validated['stock_quantity'],
            'alert_quantity' => $validated['alert_quantity'],
            'expiry_date' => $validated['expiry_date'] ?? null,
            'description' => $validated['description'] ?? null,
            'track_expiry' => $request->has('track_expiry'),
            'is_controlled' => $request->has('is_controlled'),
            'is_active' => $request->has('is_active'),
        ]);

        // فحص المخزون عند التحديث
        $this->checkStockAlert($product);

        return redirect()->route('products.index')->with('success', 'تم تحديث المنتج بنجاح.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('products.index')->with('success', 'تم حذف المنتج بنجاح.');
    }

    /**
     * دالة مسؤولة عن إرسال إشعار نقص المخزون إذا وصل المنتج للحد الأدنى
     */
    private function checkStockAlert(Product $product)
    {
        if ($product->stock_quantity <= $product->alert_quantity) {
            // منع تكرار نفس الإشعار غير المقروء لنفس المنتج
            $exists = Notification::where('user_id', auth()->id())
                ->where('is_read', 0)
                ->where('message', 'like', "%{$product->name}%")
                ->exists();

            if (!$exists) {
                Notification::create([
                    'user_id' => auth()->id(),
                    'title' => 'تنبيه نقص مخزون',
                    'message' => "الدواء/المنتج ({$product->name}) وصل للحد الأدنى. الرصيد الحالي: {$product->stock_quantity}",
                    'is_read' => 0
                ]);
            }
        }
    }
}
