<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4" dir="rtl">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 tracking-tight flex items-center gap-3">
                    <span class="p-2 bg-indigo-50 text-indigo-600 rounded-xl shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </span>
                    إضافة منتج جديد للمخزن
                </h2>
                <p class="text-xs text-slate-500 mt-1 mr-11">إدخال تفاصيل المنتج الجديد والكمية والسعر</p>
            </div>
            
            <a href="{{ route('inventory.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-200 text-slate-700 font-medium text-sm rounded-xl hover:bg-slate-300 transition-all">
                رجوع للقائمة
            </a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto py-8" dir="rtl">
        <div class="bg-white p-6 md:p-8 rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100">
            <form action="{{ route('inventory.store') }}" method="POST" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- اسم المنتج -->
                    <div class="col-span-2 md:col-span-1">
                        <label class="block text-sm font-bold text-slate-700 mb-2">اسم المنتج</label>
                        <input type="text" name="name" value="{{ old('name') }}" required 
                            class="w-full px-5 py-3 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 text-sm outline-none transition-all"
                            placeholder="أدخل اسم المنتج...">
                        @error('name')
                            <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- الفئة -->
                    <div class="col-span-2 md:col-span-1">
                        <label class="block text-sm font-bold text-slate-700 mb-2">فئة المنتج</label>
                        <select name="category_id" required 
                            class="w-full px-5 py-3 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 text-sm outline-none transition-all bg-white">
                            <option value="">اختر الفئة...</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- الكمية المتوفرة -->
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">الكمية المتوفرة</label>
                        <input type="number" name="quantity" value="{{ old('quantity', 0) }}" min="0" required 
                            class="w-full px-5 py-3 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 text-sm outline-none transition-all">
                        @error('quantity')
                            <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- سعر البيع -->
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">سعر البيع</label>
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 text-slate-400 font-medium text-sm">شيكل</span>
                            <input type="number" step="0.01" name="price" value="{{ old('price', 0.00) }}" min="0" required 
                                class="w-full px-5 py-3 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 text-sm outline-none transition-all">
                        </div>
                        @error('price')
                            <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <!-- خيارات إضافية -->
                <div class="flex items-center gap-6 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="track_expiry" value="1" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500">
                        <span class="text-sm font-medium text-slate-700">تتبع تاريخ الصلاحية</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_controlled" value="1" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500">
                        <span class="text-sm font-medium text-slate-700">دواء مراقب / مقيد</span>
                    </label>
                </div>

                <!-- زر الحفظ -->
                <div class="pt-6 border-t border-slate-100 flex items-center justify-end gap-4">
                    <a href="{{ route('inventory.index') }}" class="px-6 py-3 text-slate-500 font-semibold hover:text-slate-900 transition">
                        إلغاء
                    </a>
                    <button type="submit" class="px-8 py-3 bg-indigo-600 text-white font-bold rounded-2xl hover:bg-indigo-700 shadow-lg shadow-indigo-500/30 active:scale-95 transition-all">
                        حفظ المنتج
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>