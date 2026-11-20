<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Unit;
use App\Models\Notification; // تأكد من استدعاء موديل الإشعارات
use App\Support\ArabicSearch;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $search = $request->input('search');

        $products = Product::visibleTo($request->user())->with(['category', 'brand', 'unit'])
            // بحث غير حساس للهمزات والتاء المربوطة والتشكيل، وبأي ترتيب للكلمات
            ->when($search, fn ($query, $search) => ArabicSearch::apply($query, $search, ['name', 'barcode', 'sku']))
            ->latest()
            ->paginate(10);

        // مستخدم الفرع يرى رصيد فرعه، وصاحب الصيدلية يرى الإجمالي
        \App\Support\Stock::overlay($products);

        $categories = Category::all();
        $brands = Brand::all();
        $units = Unit::all();

        return view('products.index', compact('products', 'categories', 'brands', 'units'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'barcode' => ['nullable', 'string', 'max:100', \Illuminate\Validation\Rule::unique('products', 'barcode')->where('organization_id', $this->currentOrganizationId())],
            'category_id' => 'nullable|exists:categories,id,deleted_at,NULL',
            'brand_id' => 'nullable|exists:brands,id,deleted_at,NULL',
            'unit_id' => 'nullable|exists:units,id,deleted_at,NULL',
            'purchase_price' => 'required|numeric|min:0|lte:selling_price',
            'selling_price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'alert_quantity' => 'required|integer|min:0',
            'expiry_date' => 'nullable|date',
            'description' => 'nullable|string|max:1000',
            'track_expiry' => 'sometimes|boolean',
            'is_controlled' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ], [
            'purchase_price.lte' => 'سعر الشراء أعلى من سعر البيع. الأسعار هنا للحبة (الوحدة الأساسية): أدخل سعر الحبة الواحدة وليس العلبة.',
        ]);

        $product = Product::create([
            'organization_id' => $this->currentOrganizationId(),
            'category_id' => $validated['category_id'] ?? null,
            'brand_id' => $validated['brand_id'] ?? null,
            'unit_id' => $validated['unit_id'] ?? null,
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? null,
            'barcode' => $validated['barcode'] ?? null,
            'purchase_price' => $validated['purchase_price'],
            'selling_price' => $validated['selling_price'],
            'stock_quantity' => 0,
            'alert_quantity' => $validated['alert_quantity'],
            'expiry_date' => $validated['expiry_date'] ?? null,
            'description' => $validated['description'] ?? null,
            'track_expiry' => $request->has('track_expiry'),
            'is_controlled' => $request->has('is_controlled'),
            'is_active' => $request->has('is_active'),
        ]);

        // الرصيد الافتتاحي يدخل مستودع فرع المستخدم كحركة مخزنية
        \App\Support\Stock::move($product->id, null, (int) $validated['stock_quantity'], 'opening_stock', 'product', $product->id);
        $product->refresh();

        // فحص المخزون عند الإضافة
        $this->checkStockAlert($product);

        return redirect()->route('products.index')->with('success', 'تم إضافة المنتج بنجاح.');
    }

    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'barcode' => ['nullable', 'string', 'max:100', \Illuminate\Validation\Rule::unique('products', 'barcode')->where('organization_id', $product->organization_id)->ignore($product->id)],
            'category_id' => 'nullable|exists:categories,id,deleted_at,NULL',
            'brand_id' => 'nullable|exists:brands,id,deleted_at,NULL',
            'unit_id' => 'nullable|exists:units,id,deleted_at,NULL',
            'purchase_price' => 'required|numeric|min:0|lte:selling_price',
            'selling_price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'alert_quantity' => 'required|integer|min:0',
            'expiry_date' => 'nullable|date',
            'description' => 'nullable|string|max:1000',
            'track_expiry' => 'sometimes|boolean',
            'is_controlled' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ], [
            'purchase_price.lte' => 'سعر الشراء أعلى من سعر البيع. الأسعار هنا للحبة (الوحدة الأساسية): أدخل سعر الحبة الواحدة وليس العلبة.',
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
            'alert_quantity' => $validated['alert_quantity'],
            'expiry_date' => $validated['expiry_date'] ?? null,
            'description' => $validated['description'] ?? null,
            'track_expiry' => $request->has('track_expiry'),
            'is_controlled' => $request->has('is_controlled'),
            'is_active' => $request->has('is_active'),
        ]);

        // تعديل الرصيد من شاشة المنتج = حركة تسوية على الفرع (رصيد الفرع لمستخدم الفرع، والإجمالي لصاحب الصيدلية)
        $branchId = \App\Support\Stock::viewBranchId();
        $current = $branchId ? \App\Support\Stock::quantity($product->id, $branchId) : (int) $product->stock_quantity;
        $diff = (int) $validated['stock_quantity'] - $current;
        if ($diff !== 0) {
            \App\Support\Stock::move($product->id, $branchId, $diff, 'adjustment', 'product', $product->id, $product->name);
            $product->refresh();
        }

        // فحص المخزون عند التحديث
        $this->checkStockAlert($product);

        return redirect()->route('products.index')->with('success', 'تم تحديث المنتج بنجاح.');
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $qty = (int) \App\Support\Tenancy::bypass(fn () => \App\Models\BranchStock::where('product_id', $product->id)->sum('quantity'));
        if ($qty > 0) {
            return redirect()->route('products.index')->with('error', "لا يمكن حذف «{$product->name}» وفي المخزون منه {$qty}. بِعه أو أرجعه للمورد أو أتلفه أولاً، أو أوقفه (غير نشط) بدل الحذف.");
        }

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
