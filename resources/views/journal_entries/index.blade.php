@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- نموذج الإضافة والتعديل -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 h-fit">
        <h3 id="form-title" class="text-base font-black text-slate-800 mb-4">إنشاء قيد يومي جديد</h3>
        
        @if ($errors->any())
            <div class="mb-4 p-3 bg-rose-50 text-rose-600 rounded-xl text-xs">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="crud-form" action="{{ route('journal-entries.store') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" id="form-method" name="_method" value="POST">
            
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-600 mb-1">المنظمة التابعة لها</label>
                <select name="organization_id" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                    @foreach(\App\Models\Organization::all() as $org)
                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">رقم القيد</label>
                <input type="text" id="entry_number" name="entry_number" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs font-mono focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">التاريخ</label>
                <input type="date" id="entry_date" name="entry_date" required value="{{ date('Y-m-d') }}" class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">البيان / الوصف</label>
                <textarea id="description" name="description" rows="3" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>
            </div>
            
            <div class="flex items-center gap-2 pt-2">
                <button type="submit" id="submit-btn" class="w-full py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 shadow-md transition">حفظ القيد</button>
                <button type="button" id="cancel-btn" onclick="resetForm()" class="hidden w-full py-2.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200 transition">إلغاء</button>
            </div>
        </form>
    </div>

    <!-- الجدول -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden h-fit">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-base font-black text-slate-800">قيود اليومية المحاسبية</h3>
            <span class="text-xs text-slate-400 font-semibold">الإجمالي: {{ $entries->count() }}</span>
        </div>
        <table class="w-full text-right border-collapse text-xs">
            <thead>
                <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                    <th class="p-4 font-bold">رقم القيد</th>
                    <th class="p-4 font-bold">التاريخ</th>
                    <th class="p-4 font-bold">البيان</th>
                    <th class="p-4 font-bold text-center">التحكم</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($entries as $entry)
                <tr class="hover:bg-slate-50/50">
                    <td class="p-4 font-mono font-bold text-indigo-600">{{ $entry->entry_number }}</td>
                    <td class="p-4 text-slate-600">{{ $entry->entry_date }}</td>
                    <td class="p-4 text-slate-800 font-medium">{{ $entry->description }}</td>
                    <td class="p-4 text-center space-x-2 space-x-reverse">
                        <!-- زر عرض التفاصيل داخل نفس الصفحة -->
                        <button type="button" onclick="showDetails('{{ $entry->entry_number }}', '{{ $entry->entry_date }}', '{{ $entry->description }}', '{{ $entry->organization->name ?? 'غير محددة' }}')" class="text-emerald-600 hover:underline font-bold">التفاصيل</button>
                        <button type="button" onclick="editItem('{{ $entry->id }}', '{{ $entry->entry_number }}', '{{ $entry->entry_date }}', '{{ $entry->description }}', '{{ $entry->organization_id }}')" class="text-indigo-600 hover:underline font-bold">تعديل</button>
                        <form action="{{ route('journal-entries.destroy', $entry->id) }}" method="POST" class="inline-block" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-rose-600 hover:underline font-bold">حذف</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="p-6 text-center text-slate-400">لا توجد قيود مسجلة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- نافذة عرض التفاصيل المنبثقة (Modal) داخل نفس الصفحة -->
<div id="details-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6 space-y-4">
        <div class="flex justify-between items-center border-b pb-3">
            <h3 class="text-base font-black text-slate-800">تفاصيل القيد المحاسبي</h3>
            <button onclick="closeDetails()" class="text-slate-400 hover:text-slate-600 font-bold text-sm">✕</button>
        </div>
        <div class="space-y-3 text-xs text-slate-600">
            <div class="flex justify-between bg-slate-50 p-3 rounded-xl">
                <span class="font-bold">رقم القيد:</span>
                <span id="modal-number" class="font-mono font-bold text-indigo-600"></span>
            </div>
            <div class="flex justify-between bg-slate-50 p-3 rounded-xl">
                <span class="font-bold">التاريخ:</span>
                <span id="modal-date"></span>
            </div>
            <div class="flex justify-between bg-slate-50 p-3 rounded-xl">
                <span class="font-bold">المنظمة:</span>
                <span id="modal-org"></span>
            </div>
            <div class="bg-slate-50 p-3 rounded-xl space-y-1">
                <span class="font-bold block">البيان / الوصف:</span>
                <p id="modal-desc" class="text-slate-800 font-medium"></p>
            </div>
        </div>
        <div class="pt-2 flex justify-end">
            <button onclick="closeDetails()" class="px-5 py-2 bg-slate-200 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-300 transition">إغلاق</button>
        </div>
    </div>
</div>

<script>
    function showDetails(number, date, desc, orgName) {
        document.getElementById('modal-number').innerText = number;
        document.getElementById('modal-date').innerText = date;
        document.getElementById('modal-desc').innerText = desc;
        document.getElementById('modal-org').innerText = orgName;
        
        const modal = document.getElementById('details-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeDetails() {
        const modal = document.getElementById('details-modal');
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }

    function editItem(id, number, date, desc, orgId) {
        const form = document.getElementById('crud-form');
        form.action = `/journal-entries/${id}`;
        document.getElementById('form-method').value = 'PUT';
        document.getElementById('entry_number').value = number;
        document.getElementById('entry_date').value = date;
        document.getElementById('description').value = desc;
        
        const orgSelect = document.querySelector('select[name="organization_id"]');
        if (orgSelect && orgId) {
            orgSelect.value = orgId;
        }

        document.getElementById('form-title').innerText = 'تعديل القيد';
        const btn = document.getElementById('submit-btn');
        btn.innerText = 'تحديث البيانات';
        btn.className = 'w-full py-2.5 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 shadow-md transition';
        document.getElementById('cancel-btn').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        const form = document.getElementById('crud-form');
        form.action = "{{ route('journal-entries.store') }}";
        document.getElementById('form-method').value = 'POST';
        form.reset();
        document.getElementById('form-title').innerText = 'إنشاء قيد يومي جديد';
        const btn = document.getElementById('submit-btn');
        btn.innerText = 'حفظ القيد';
        btn.className = 'w-full py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 shadow-md transition';
        document.getElementById('cancel-btn').classList.add('hidden');
    }
</script>
@endsection