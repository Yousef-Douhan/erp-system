@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header & Action Button -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-extrabold text-slate-800">إدارة المنتجات (Products)</h2>
            <p class="text-xs text-slate-400 mt-1">إضافة وإدارة الأدوية والسلع مع الأسعار والمخزون</p>
        </div>
        <button onclick="openModal('addProductModal')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-md shadow-indigo-100 transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            إضافة منتج جديد
        </button>
    </div>

    <!-- شريط البحث (Search Bar) -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
        <form action="{{ route('products.index') }}" method="GET" class="flex items-center gap-3">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="ابحث باسم المنتج، الباركود، أو SKU..." class="w-full pr-9 pl-4 text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-xl transition-all">
                بحث
            </button>
            @if(request('search'))
                <a href="{{ route('products.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition-all">
                    إعادة ضبط
                </a>
            @endif
        </form>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 text-emerald-600 text-xs font-semibold border border-emerald-100">
            {{ session('success') }}
        </div>
    @endif

    <!-- Products Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase">
                    <th class="p-4">#</th>
                    <th class="p-4">المنتج</th>
                    <th class="p-4">الباركود / SKU</th>
                    <th class="p-4">سعر الشراء</th>
                    <th class="p-4">سعر البيع</th>
                    <th class="p-4">المخزون</th>
                    <th class="p-4">الوحدة</th>
                    <th class="p-4">الحالة</th>
                    <th class="p-4 text-left">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                @forelse($products as $product)
                    <tr class="hover:bg-slate-50/50 transition-all">
                        <td class="p-4 font-semibold text-slate-400">{{ $loop->iteration }}</td>
                        <td class="p-4">
                            <div class="font-bold text-slate-800">{{ $product->name }}</div>
                            <div class="text-[10px] text-slate-400">{{ $product->brand->name ?? '' }}</div>
                        </td>
                        <td class="p-4 font-mono text-xs text-slate-500">
                            <div>{{ $product->barcode ?? '-' }}</div>
                            <div class="text-[10px] text-slate-400">{{ $product->sku ?? '' }}</div>
                        </td>
                        <td class="p-4 font-mono text-slate-600">{{ number_format($product->purchase_price ?? 0, 2) }}</td>
                        <td class="p-4 font-mono font-bold text-indigo-600">{{ number_format($product->selling_price ?? 0, 2) }}</td>
                        <td class="p-4 font-mono text-xs">
                            <span class="px-2.5 py-1 bg-slate-100 rounded-lg font-semibold">{{ $product->stock_quantity ?? 0 }}</span>
                        </td>
                        <td class="p-4 text-xs">{{ $product->unit->name ?? '-' }}</td>
                        <td class="p-4">
                            @if(isset($product->is_active) && $product->is_active)
                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-600 rounded-lg text-xs font-bold">نشط</span>
                            @else
                                <span class="px-2.5 py-1 bg-slate-100 text-slate-500 rounded-lg text-xs font-bold">غير نشط</span>
                            @endif
                        </td>
                        <td class="p-4 text-left space-x-1 space-x-reverse whitespace-nowrap">
                            <!-- زر الوحدات -->
                            <a href="{{ route('products.units.index', $product->id) }}" class="px-3 py-1 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 text-xs font-semibold rounded-lg transition-all ml-1">
                                وحدات
                            </a>

                            <!-- زر التعديل -->
                            <button onclick='openEditModal(@json($product))' class="px-3 py-1 bg-blue-50 text-blue-600 hover:bg-blue-100 text-xs font-semibold rounded-lg transition-all ml-1">
                                تعديل
                            </button>

                            <!-- زر الحذف -->
                            <form action="{{ route('products.destroy', $product->id) }}" method="POST" class="inline-block" onsubmit="return confirm('هل أنت متأكد من حذف المنتج؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1 bg-rose-50 text-rose-600 hover:bg-rose-100 text-xs font-semibold rounded-lg transition-all">
                                    حذف
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="p-8 text-center text-slate-400 text-xs">لا توجد منتجات مطابقة للبحث.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if(method_exists($products, 'links'))
            <div class="p-4 border-t border-slate-100">
                {{ $products->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal نافذة الإضافة -->
<div id="addProductModal" class="fixed inset-0 bg-slate-900/50 z-50 hidden flex items-center justify-center p-4 overflow-y-auto">
    <!-- محتوى نافذة الإضافة (نفس الكود السابق تماماً) -->
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl my-8">
        <h3 class="text-base font-bold text-slate-800">إضافة منتج جديد</h3>
        <form action="{{ route('products.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">اسم المنتج *</label>
                <input type="text" name="name" required class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">SKU</label>
                    <input type="text" name="sku" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">الباركود</label>
                    <input type="text" name="barcode" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">التصنيف</label>
                    <select name="category_id" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                        <option value="">بدون</option>
                        @isset($categories)
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        @endisset
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">الماركة</label>
                    <select name="brand_id" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                        <option value="">بدون</option>
                        @isset($brands)
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                            @endforeach
                        @endisset
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">الوحدة</label>
                    <select name="unit_id" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                        <option value="">بدون</option>
                        @isset($units)
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                            @endforeach
                        @endisset
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">سعر الشراء *</label>
                    <input type="number" step="0.01" name="purchase_price" value="0" required class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">سعر البيع *</label>
                    <input type="number" step="0.01" name="selling_price" value="0" required class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">كمية المخزون *</label>
                    <input type="number" name="stock_quantity" value="0" required class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">حد التنبيه *</label>
                    <input type="number" name="alert_quantity" value="5" required class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">تاريخ الصلاحية</label>
                <input type="date" name="expiry_date" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الوصف</label>
                <textarea name="description" rows="2" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600"></textarea>
            </div>
            <div class="flex items-center gap-4 pt-2">
                <label class="flex items-center gap-2 text-xs font-bold text-slate-600 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    نشط
                </label>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('addProductModal')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl">حفظ</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal نافذة التعديل -->
<div id="editProductModal" class="fixed inset-0 bg-slate-900/50 z-50 hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl my-8">
        <h3 class="text-base font-bold text-slate-800">تعديل المنتج</h3>
        <form id="editProductForm" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">اسم المنتج *</label>
                <input type="text" id="edit_name" name="name" required class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">SKU</label>
                    <input type="text" id="edit_sku" name="sku" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">الباركود</label>
                    <input type="text" id="edit_barcode" name="barcode" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">التصنيف</label>
                    <select id="edit_category_id" name="category_id" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                        <option value="">بدون</option>
                        @isset($categories)
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        @endisset
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">الماركة</label>
                    <select id="edit_brand_id" name="brand_id" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                        <option value="">بدون</option>
                        @isset($brands)
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                            @endforeach
                        @endisset
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">الوحدة</label>
                    <select id="edit_unit_id" name="unit_id" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                        <option value="">بدون</option>
                        @isset($units)
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                            @endforeach
                        @endisset
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">سعر الشراء *</label>
                    <input type="number" step="0.01" id="edit_purchase_price" name="purchase_price" required class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">سعر البيع *</label>
                    <input type="number" step="0.01" id="edit_selling_price" name="selling_price" required class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">كمية المخزون *</label>
                    <input type="number" id="edit_stock_quantity" name="stock_quantity" required class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">حد التنبيه *</label>
                    <input type="number" id="edit_alert_quantity" name="alert_quantity" required class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">تاريخ الصلاحية</label>
                <input type="date" id="edit_expiry_date" name="expiry_date" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الوصف</label>
                <textarea id="edit_description" name="description" rows="2" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600"></textarea>
            </div>
            <div class="flex items-center gap-4 pt-2">
                <label class="flex items-center gap-2 text-xs font-bold text-slate-600 cursor-pointer">
                    <input type="checkbox" id="edit_is_active" name="is_active" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    نشط
                </label>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('editProductModal')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl">تحديث</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { 
        document.getElementById(id).classList.remove('hidden'); 
    }
    
    function closeModal(id) { 
        document.getElementById(id).classList.add('hidden'); 
    }

    function openEditModal(product) {
        document.getElementById('editProductForm').action = '/products/' + product.id;
        
        document.getElementById('edit_name').value = product.name || '';
        document.getElementById('edit_sku').value = product.sku || '';
        document.getElementById('edit_barcode').value = product.barcode || '';
        document.getElementById('edit_category_id').value = product.category_id || '';
        document.getElementById('edit_brand_id').value = product.brand_id || '';
        document.getElementById('edit_unit_id').value = product.unit_id || '';
        document.getElementById('edit_purchase_price').value = product.purchase_price || 0;
        document.getElementById('edit_selling_price').value = product.selling_price || 0;
        document.getElementById('edit_stock_quantity').value = product.stock_quantity || 0;
        document.getElementById('edit_alert_quantity').value = product.alert_quantity || 0;
        document.getElementById('edit_expiry_date').value = product.expiry_date ? product.expiry_date.split('T')[0] : '';
        document.getElementById('edit_description').value = product.description || '';
        document.getElementById('edit_is_active').checked = product.is_active == 1;

        openModal('editProductModal');
    }
</script>
@endsection