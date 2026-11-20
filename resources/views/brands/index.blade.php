@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto">@include('partials.subnav', ['set' => 'catalog'])</div>
<div class="py-6" dir="rtl">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <!-- الهيدر والزر -->
        <div class="flex justify-between items-center mb-6 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 tracking-tight">إدارة الماركات (Brands)</h2>
                <span class="text-xs bg-indigo-50 text-indigo-600 px-3 py-1 rounded-full font-semibold mt-1 inline-block">عدد الماركات: {{ $brands->total() ?? 0 }}</span>
            </div>
            <button onclick="openCreateModal()" class="px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition text-sm">
                + إضافة ماركة جديدة
            </button>
        </div>

        <!-- جدول البيانات -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-right border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-sm border-b border-slate-100">
                            <th class="p-4">اسم الماركة</th>
                            <th class="p-4">الحالة</th>
                            <th class="p-4 text-left">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 text-sm">
                        @forelse($brands as $brand)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="p-4 font-bold text-slate-800">{{ $brand->name }}</td>
                                <td class="p-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $brand->is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                                        {{ $brand->is_active ? 'نشط' : 'معطل' }}
                                    </span>
                                </td>
                                <td class="p-4 text-left flex justify-end gap-2">
                                    <button onclick="openEditModal({{ $brand->id }}, '{{ addslashes($brand->name) }}', {{ $brand->is_active ? 1 : 0 }})" class="px-3 py-1.5 bg-amber-50 text-amber-600 rounded-lg text-xs font-bold hover:bg-amber-100 transition">تعديل</button>
                                    <button onclick="deleteBrand({{ $brand->id }})" class="px-3 py-1.5 bg-rose-50 text-rose-600 rounded-lg text-xs font-bold hover:bg-rose-100 transition">حذف</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="p-8 text-center text-slate-400 font-medium">لا توجد أي ماركات مسجلة حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($brands, 'hasPages') && $brands->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $brands->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Modal الإضافة والتعديل -->
<div id="brandModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50" dir="rtl">
    <div class="bg-white w-full max-w-lg p-6 rounded-3xl shadow-2xl border border-slate-100 m-4">
        <div class="flex justify-between items-center mb-4 border-b pb-3">
            <h3 id="modalTitle" class="font-bold text-lg text-slate-800">إضافة ماركة جديدة</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 font-bold text-xl">&times;</button>
        </div>

        <form id="brandForm" onsubmit="submitForm(event)">
            <input type="hidden" id="brandId">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">اسم الماركة</label>
                    <input type="text" id="name" required class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none text-sm">
                </div>
                <div class="flex items-center gap-2 pt-2">
                    <input type="checkbox" id="is_active" value="1" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500">
                    <label for="is_active" class="text-sm font-medium text-slate-700">ماركة نشطة</label>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 pt-3 border-t">
                <button type="button" onclick="closeModal()" class="px-5 py-2.5 bg-slate-100 text-slate-600 text-sm font-medium rounded-xl hover:bg-slate-200 transition">إلغاء</button>
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition">حفظ</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
    axios.defaults.headers.common['X-CSRF-TOKEN'] = '{{ csrf_token() }}';
    const modal = document.getElementById('brandModal');
    const form = document.getElementById('brandForm');
    let isEdit = false;

    function openCreateModal() {
        isEdit = false;
        document.getElementById('modalTitle').innerText = 'إضافة ماركة جديدة';
        form.reset();
        document.getElementById('brandId').value = '';
        document.getElementById('is_active').checked = true; // افتراضياً نشط عند الإضافة
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function openEditModal(id, name, isActive) {
        isEdit = true;
        document.getElementById('modalTitle').innerText = 'تعديل الماركة: ' + name;
        document.getElementById('brandId').value = id;
        document.getElementById('name').value = name;
        
        // ضبط الـ Checkbox بدقة
        document.getElementById('is_active').checked = (isActive == 1 || isActive === true);
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal() {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }

    function submitForm(e) {
        e.preventDefault();
        const id = document.getElementById('brandId').value;
        
        // إرسال البيانات كـ JSON لضمان وصول قيمة الـ Checkbox (1 أو 0) بثبات كامل
        const data = {
            name: document.getElementById('name').value,
            is_active: document.getElementById('is_active').checked ? 1 : 0
        };

        let url = isEdit ? `/brands/${id}` : '{{ route("brands.store") }}';
        let method = isEdit ? 'put' : 'post';

        axios[method](url, data)
            .then(response => {
                location.reload();
            })
            .catch(error => {
                console.error(error.response?.data);
                alert(error.response?.data?.message || 'حدث خطأ أثناء الحفظ، تأكد من البيانات المدخلة.');
            });
    }

    function deleteBrand(id) {
        if(confirm('هل أنت متأكد من حذف هذه الماركة؟')) {
            axios.delete(`/brands/${id}`)
                .then(response => location.reload())
                .catch(error => alert(error.response?.data?.message || 'لا يمكن حذف الماركة.'));
        }
    }
</script>
@endsection