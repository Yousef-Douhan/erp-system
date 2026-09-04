@extends('layouts.app')

@section('content')
<div class="py-6" dir="rtl">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <!-- الهيدر والزر -->
        <div class="flex justify-between items-center mb-6 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 tracking-tight">إدارة المصنعين (Manufacturers)</h2>
                <span class="text-xs bg-indigo-50 text-indigo-600 px-3 py-1 rounded-full font-semibold mt-1 inline-block">عدد المصنعين: {{ $manufacturers->total() ?? 0 }}</span>
            </div>
            <button onclick="openCreateModal()" class="px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition text-sm">
                + إضافة مصنع جديد
            </button>
        </div>

        <!-- جدول البيانات -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-right border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-sm border-b border-slate-100">
                            <th class="p-4">اسم المصنع</th>
                            <th class="p-4">تفاصيل التواصل</th>
                            <th class="p-4 text-left">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 text-sm">
                        @forelse($manufacturers as $manufacturer)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="p-4 font-bold text-slate-800">{{ $manufacturer->name }}</td>
                                <td class="p-4 text-slate-500">{{ $manufacturer->contact_details ?? 'لا توجد تفاصيل' }}</td>
                                <td class="p-4 text-left flex justify-end gap-2">
                                    <button onclick="openEditModal({{ $manufacturer->id }}, '{{ addslashes($manufacturer->name) }}', '{{ addslashes($manufacturer->contact_details ?? '') }}')" class="px-3 py-1.5 bg-amber-50 text-amber-600 rounded-lg text-xs font-bold hover:bg-amber-100 transition">تعديل</button>
                                    <button onclick="deleteManufacturer({{ $manufacturer->id }})" class="px-3 py-1.5 bg-rose-50 text-rose-600 rounded-lg text-xs font-bold hover:bg-rose-100 transition">حذف</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="p-8 text-center text-slate-400 font-medium">لا توجد أي مصانع مسجلة حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($manufacturers, 'hasPages') && $manufacturers->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $manufacturers->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Modal الإضافة والتعديل -->
<div id="manufacturerModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50" dir="rtl">
    <div class="bg-white w-full max-w-lg p-6 rounded-3xl shadow-2xl border border-slate-100 m-4">
        <div class="flex justify-between items-center mb-4 border-b pb-3">
            <h3 id="modalTitle" class="font-bold text-lg text-slate-800">إضافة مصنع جديد</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 font-bold text-xl">&times;</button>
        </div>

        <form id="manufacturerForm" onsubmit="submitForm(event)">
            <input type="hidden" id="manufacturerId">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">اسم المصنع</label>
                    <input type="text" id="name" required class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">تفاصيل التواصل (اختياري)</label>
                    <textarea id="contact_details" rows="3" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none text-sm resize-none"></textarea>
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
    const modal = document.getElementById('manufacturerModal');
    const form = document.getElementById('manufacturerForm');
    let isEdit = false;

    function openCreateModal() {
        isEdit = false;
        document.getElementById('modalTitle').innerText = 'إضافة مصنع جديد';
        form.reset();
        document.getElementById('manufacturerId').value = '';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function openEditModal(id, name, contactDetails) {
        isEdit = true;
        document.getElementById('modalTitle').innerText = 'تعديل المصنع: ' + name;
        document.getElementById('manufacturerId').value = id;
        document.getElementById('name').value = name;
        document.getElementById('contact_details').value = contactDetails === 'null' ? '' : contactDetails;
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal() {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }

    function submitForm(e) {
        e.preventDefault();
        const id = document.getElementById('manufacturerId').value;
        
        const data = {
            name: document.getElementById('name').value,
            contact_details: document.getElementById('contact_details').value
        };

        let url = isEdit ? `/manufacturers/${id}` : '{{ route("manufacturers.store") }}';
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

    function deleteManufacturer(id) {
        if(confirm('هل أنت متأكد من حذف هذا المصنع؟')) {
            axios.delete(`/manufacturers/${id}`)
                .then(response => location.reload())
                .catch(error => alert(error.response?.data?.message || 'لا يمكن حذف المصنع.'));
        }
    }
</script>
@endsection