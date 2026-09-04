<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4" dir="rtl">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 tracking-tight flex items-center gap-3">
                    <span class="p-2 bg-indigo-50 text-indigo-600 rounded-xl shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </span>
                    تعديل المنتج: <span class="text-indigo-600">{{ $item->name }}</span>
                </h2>
                <p class="text-xs text-slate-500 mt-1 mr-11">تحديث بيانات المنتج والكمية والسعر</p>
            </div>
            
            <a href="{{ route('inventory.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-200 text-slate-700 font-medium text-sm rounded-xl hover:bg-slate-300 transition-all">
                رجوع للقائمة
            </a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto py-8" dir="rtl">
        <div class="bg-white p-6 md:p-8 rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100">
            <form action="{{ route('inventory.update', ['inventory' => $item->id]) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- اسم المنتج -->
                    <div class="col-span-2 md:col-span-1">
                        <label class="block text-sm font-bold text-slate-700 mb-2">اسم المنتج</label>
                        <input type="text" name="name" value="{{ old('name', $item->name) }}" required 
                            class="w-full px-5 py-3 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 text-sm outline-none transition-all">
                        @error('name')
                            <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- الفئة -->
                    <div class="col-span-2 md:col-span-1">
                        <label class="block text-sm font-bold text-slate-700 mb-2">الفئة</label>
                        <select name="category_id" required 
                            class="w-full px-5 py-3 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 text-sm outline-none transition-all bg-white">
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id', $item->category_id) == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- الكمية -->
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">الكمية الحالية</label>
                        <input type="number" name="quantity" value="{{ old('quantity', $item->quantity) }}" min="0" required 
                            class="w-full px-5 py-3 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 text-sm outline-none transition-all">
                        @error('quantity')
                            <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- السعر -->
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">سعر البيع</label>
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 text-slate-400 font-medium text-sm">شيكل</span>
                            <input type="number" step="0.01" name="price" value="{{ old('price', $item->price) }}" min="0" required 
                                class="w-full px-5 py-3 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 text-sm outline-none transition-all">
                        </div>
                        @error('price')
                            <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <!-- أزرار التحكم -->
                <div class="pt-6 border-t border-slate-100 flex items-center justify-end gap-4">
                    <a href="{{ route('inventory.index') }}" class="px-6 py-3 text-slate-500 font-semibold hover:text-slate-900 transition">
                        إلغاء
                    </a>
                    
                    <button type="submit" class="px-8 py-3 bg-indigo-600 text-white font-bold rounded-2xl hover:bg-indigo-700 shadow-lg shadow-indigo-500/30 active:scale-95 transition-all">
                        حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>