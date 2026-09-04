@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    
    <!-- رأس الصفحة -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-extrabold text-slate-800">إدارة فواتير المشتريات</h2>
            <p class="text-xs text-slate-400 mt-0.5">عرض، إضافة، تعديل، وعرض تفاصيل المشتريات من مكان واحد.</p>
        </div>
        
        @if(request('edit') || request('show'))
            <a href="{{ route('purchases.index') }}" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-xl transition-all">
                إلغاء والعودة للإضافة
            </a>
        @endif
    </div>

    <!-- رسائل التنبيه -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 text-emerald-600 text-xs font-semibold border border-emerald-100">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 text-xs space-y-1">
            <p class="font-bold">يرجى تصحيح الأخطاء التالية:</p>
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- القسم الأيمن: نموذج الإضافة أو التعديل للفاتورة الرئيسية -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4 sticky top-6">
                <h3 class="text-sm font-bold text-slate-800 pb-2 border-b border-slate-100">
                    {{ isset($editPurchase) ? 'تعديل فاتورة رقم: ' . $editPurchase->invoice_number : 'إضافة فاتورة شراء جديدة' }}
                </h3>

                <form action="{{ isset($editPurchase) ? route('purchases.update', $editPurchase->id) : route('purchases.store') }}" method="POST" class="space-y-4">
                    @csrf
                    @if(isset($editPurchase))
                        @method('PUT')
                    @endif

                    <!-- الفرع -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700">الفرع</label>
                        <select name="branch_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-700 focus:ring-2 focus:ring-indigo-500">
                            <option value="">اختر الفرع...</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ (isset($editPurchase) && $editPurchase->branch_id == $branch->id) || old('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- المورد -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700">المورد</label>
                        <select name="supplier_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-700 focus:ring-2 focus:ring-indigo-500">
                            <option value="">اختر المورد...</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ (isset($editPurchase) && $editPurchase->supplier_id == $supplier->id) || old('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- رقم الفاتورة -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700">رقم الفاتورة</label>
                        <input type="text" name="invoice_number" value="{{ isset($editPurchase) ? $editPurchase->invoice_number : old('invoice_number') }}" required placeholder="أدخل رقم الفاتورة..." class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-700 focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <!-- إجمالي المبلغ -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700">إجمالي المبلغ</label>
                        <input type="number" step="0.01" name="total" value="{{ isset($editPurchase) ? $editPurchase->total : old('total', 0) }}" required placeholder="0.00" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-700 focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <!-- المبلغ المدفوع -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700">المبلغ المدفوع</label>
                        <input type="number" step="0.01" name="paid_amount" value="{{ isset($editPurchase) ? $editPurchase->paid_amount : old('paid_amount', 0) }}" placeholder="0.00" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-700 focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <!-- زر الحفظ -->
                    <div class="pt-2">
                        <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition-all">
                            {{ isset($editPurchase) ? 'تحديث الفاتورة' : 'حفظ الفاتورة الجديدة' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- القسم الأيسر: جدول العرض أو تفاصيل الفاتورة وعناصرها -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- إذا تم طلب عرض تفاصيل فاتورة (Show) مع عناصرها -->
            @if(isset($showPurchase))
                <div class="bg-indigo-50/60 border border-indigo-100 rounded-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-indigo-100 pb-3">
                        <h3 class="text-sm font-bold text-indigo-900">تفاصيل الفاتورة: {{ $showPurchase->invoice_number }}</h3>
                        <a href="{{ route('purchases.index') }}" class="text-xs text-indigo-600 hover:underline font-bold">إغلاق التفاصيل</a>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-xs text-slate-700">
                        <p><span class="font-bold text-slate-500">المورد:</span> {{ $showPurchase->supplier->name ?? '---' }}</p>
                        <p><span class="font-bold text-slate-500">الفرع:</span> {{ $showPurchase->branch->name ?? '---' }}</p>
                        <p><span class="font-bold text-slate-500">الموظف:</span> {{ $showPurchase->employee->name ?? '---' }}</p>
                        <p><span class="font-bold text-slate-500">الحالة:</span> {{ $showPurchase->status }}</p>
                        <p><span class="font-bold text-slate-500">الإجمالي:</span> <span class="font-bold text-slate-900">{{ number_format($showPurchase->total, 2) }}</span></p>
                        <p><span class="font-bold text-slate-500">المدفوع:</span> <span class="font-bold text-emerald-600">{{ number_format($showPurchase->paid_amount, 2) }}</span></p>
                    </div>

                    <!-- جدول منتجات / عناصر الفاتورة (Purchase Items) -->
                    <div class="mt-4 pt-4 border-t border-indigo-100 space-y-3">
                        <h4 class="text-xs font-bold text-slate-800">منتجات الفاتورة (Purchase Items)</h4>
                        
                        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-50 text-slate-500 font-bold text-right">
                                    <tr>
                                        <th class="px-3 py-2">المنتج</th>
                                        <th class="px-3 py-2">رقم التشغيلة</th>
                                        <th class="px-3 py-2">الصلاحية</th>
                                        <th class="px-3 py-2">الكمية</th>
                                        <th class="px-3 py-2">السعر</th>
                                        <th class="px-3 py-2">الإجمالي</th>
                                        <th class="px-3 py-2 text-center">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 text-slate-700">
                                    @forelse($showPurchase->items as $item)
                                        <tr>
                                            <td class="px-3 py-2 font-semibold">{{ $item->product->name ?? 'منتج غير محدد' }}</td>
                                            <td class="px-3 py-2">{{ $item->batch_number ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $item->expiry_date ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $item->quantity }}</td>
                                            <td class="px-3 py-2">{{ number_format($item->unit_cost, 2) }}</td>
                                            <td class="px-3 py-2 font-bold">{{ number_format($item->total, 2) }}</td>
                                            <td class="px-3 py-2 text-center">
                                                <form action="{{ route('purchase-items.destroy', $item->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا المنتج؟')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-rose-600 hover:underline">حذف</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-3 py-4 text-center text-slate-400">لا توجد منتجات مضافة لهذه الفاتورة بعد.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- نموذج سريع لإضافة منتج لهذه الفاتورة (محدث بقائمة منسدلة لأسماء المنتجات) -->
                        <form action="{{ route('purchase-items.store', $showPurchase->id) }}" method="POST" class="grid grid-cols-1 md:grid-cols-6 gap-2 pt-2">
                            @csrf
                            
                            <!-- قائمة منسدلة لاختيار اسم المنتج بدلاً من الرقم الثابت -->
                            <select name="product_id" required class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs bg-white text-slate-700">
                                <option value="">اختر المنتج...</option>
                                @foreach(\App\Models\Product::all() as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>

                            <input type="text" name="batch_number" placeholder="رقم التشغيلة" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                            <input type="date" name="expiry_date" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                            <input type="number" name="quantity" placeholder="الكمية" required class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                            <input type="number" step="0.01" name="unit_cost" placeholder="سعر الوحدة" required class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                            
                            <button type="submit" class="md:col-span-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg py-1.5 text-xs">
                                + إضافة
                            </button>
                        </form>
                    </div>

                </div>
            @endif

            <!-- جدول عرض الفواتير الرئيسية (Index) -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50 text-slate-500 text-xs font-bold text-right">
                        <tr>
                            <th class="px-4 py-3">رقم الفاتورة</th>
                            <th class="px-4 py-3">المورد</th>
                            <th class="px-4 py-3">الإجمالي</th>
                            <th class="px-4 py-3">المدفوع</th>
                            <th class="px-4 py-3">الحالة</th>
                            <th class="px-4 py-3 text-center">التحكم</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-xs text-slate-700">
                        @forelse($purchases as $purchase)
                            <tr>
                                <td class="px-4 py-3 font-bold text-slate-900">
                                    <a href="{{ route('purchases.index', ['show' => $purchase->id]) }}" class="text-indigo-600 hover:underline">
                                        {{ $purchase->invoice_number }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">{{ $purchase->supplier->name ?? '---' }}</td>
                                <td class="px-4 py-3 font-bold">{{ number_format($purchase->total, 2) }}</td>
                                <td class="px-4 py-3 text-emerald-600 font-semibold">{{ number_format($purchase->paid_amount, 2) }}</td>
                                <td class="px-4 py-3">
                                    @if($purchase->status == 'paid')
                                        <span class="px-2 py-0.5 font-bold bg-emerald-50 text-emerald-600 rounded-md">مدفوعة</span>
                                    @elseif($purchase->status == 'partial')
                                        <span class="px-2 py-0.5 font-bold bg-amber-50 text-amber-600 rounded-md">جزئي</span>
                                    @else
                                        <span class="px-2 py-0.5 font-bold bg-rose-50 text-rose-600 rounded-md">غير مدفوعة</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center flex items-center justify-center gap-1.5">
                                    <!-- زر العرض (Show) -->
                                    <a href="{{ route('purchases.index', ['show' => $purchase->id]) }}" class="text-slate-600 bg-slate-100 hover:bg-slate-200 px-2 py-1 rounded-lg font-semibold">عرض</a>
                                    
                                    <!-- زر التعديل (Edit) -->
                                    <a href="{{ route('purchases.index', ['edit' => $purchase->id]) }}" class="text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-2 py-1 rounded-lg font-semibold">تعديل</a>
                                    
                                    <!-- زر الحذف (Destroy) -->
                                    <form action="{{ route('purchases.destroy', $purchase->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 bg-rose-50 hover:bg-rose-100 px-2 py-1 rounded-lg font-semibold">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">لا توجد فواتير مسجلة.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if($purchases->hasPages())
                    <div class="p-3 border-t border-slate-200">
                        {{ $purchases->links() }}
                    </div>
                @endif
            </div>

        </div>

    </div>
</div>
@endsection