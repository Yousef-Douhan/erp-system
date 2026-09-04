@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- نموذج الإضافة والتعديل -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 h-fit">
        <h3 id="form-title" class="text-base font-black text-slate-800 mb-4">إضافة حساب جديد</h3>
        
        <!-- عرض أخطاء التحقق إن وجدت -->
        @if ($errors->any())
            <div class="mb-4 p-3 bg-rose-50 text-rose-600 rounded-xl text-xs">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="crud-form" action="{{ route('accounts.store') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" id="form-method" name="_method" value="POST">
            
            <!-- المنظمة التابعة لها -->
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-600 mb-1">المنظمة التابعة لها</label>
                <select name="organization_id" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                    @foreach(\App\Models\Organization::all() as $org)
                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <!-- الحساب الرئيسي (Parent Account) -->
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الحساب الرئيسي (اختياري)</label>
                <select id="parent_id" name="parent_id" class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                    <option value="">-- بدون حساب رئيسي (حساب رئيسي جذري) --</option>
                    @foreach(\App\Models\Account::whereNull('parent_id')->get() as $parentAcc)
                        <option value="{{ $parentAcc->id }}">{{ $parentAcc->name }} ({{ $parentAcc->account_code }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">اسم الحساب</label>
                <input type="text" id="name" name="name" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">كود الحساب</label>
                <input type="text" id="account_code" name="account_code" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs font-mono focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">نوع الحساب</label>
                <select id="account_type" name="account_type" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                    <option value="asset">أصول (Asset)</option>
                    <option value="liability">التزامات (Liability)</option>
                    <option value="equity">حقوق ملكية (Equity)</option>
                    <option value="revenue">إيرادات (Revenue)</option>
                    <option value="expense">مصروفات (Expense)</option>
                </select>
            </div>
            
            <div class="flex items-center gap-2 pt-2">
                <button type="submit" id="submit-btn" class="w-full py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 shadow-md transition">حفظ الحساب</button>
                <button type="button" id="cancel-btn" onclick="resetForm()" class="hidden w-full py-2.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200 transition">إلغاء</button>
            </div>
        </form>
    </div>

    <!-- الجدول -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-base font-black text-slate-800">إدارة دليل الحسابات</h3>
            <span class="text-xs text-slate-400 font-semibold">الإجمالي: {{ $accounts->count() }}</span>
        </div>
        <table class="w-full text-right border-collapse text-xs">
            <thead>
                <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                    <th class="p-4 font-bold">الكود</th>
                    <th class="p-4 font-bold">اسم الحساب</th>
                    <th class="p-4 font-bold">النوع</th>
                    <th class="p-4 font-bold text-center">التحكم</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($accounts as $account)
                <tr class="hover:bg-slate-50/50">
                    <td class="p-4 font-mono font-bold text-indigo-600">{{ $account->account_code }}</td>
                    <td class="p-4 font-bold text-slate-800">{{ $account->name }}</td>
                    <td class="p-4"><span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 font-semibold">{{ $account->account_type }}</span></td>
                    <td class="p-4 text-center space-x-2 space-x-reverse">
                        <button type="button" onclick="editItem('{{ $account->id }}', '{{ $account->name }}', '{{ $account->account_code }}', '{{ $account->account_type }}', '{{ $account->parent_id }}', '{{ $account->organization_id }}')" class="text-indigo-600 hover:underline font-bold">تعديل</button>
                        <form action="{{ route('accounts.destroy', $account->id) }}" method="POST" class="inline-block" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-rose-600 hover:underline font-bold">حذف</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="p-6 text-center text-slate-400">لا توجد حسابات مسجلة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
    function editItem(id, name, code, type, parentId, organizationId) {
        const form = document.getElementById('crud-form');
        form.action = `/accounts/${id}`;
        document.getElementById('form-method').value = 'PUT';
        
        document.getElementById('name').value = name;
        document.getElementById('account_code').value = code;
        document.getElementById('account_type').value = type;
        document.getElementById('parent_id').value = parentId && parentId !== 'null' ? parentId : '';
        
        const orgSelect = document.querySelector('select[name="organization_id"]');
        if (orgSelect && organizationId) {
            orgSelect.value = organizationId;
        }

        document.getElementById('form-title').innerText = 'تعديل الحساب';
        
        const btn = document.getElementById('submit-btn');
        btn.innerText = 'تحديث البيانات';
        btn.className = 'w-full py-2.5 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 shadow-md transition';
        
        document.getElementById('cancel-btn').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        const form = document.getElementById('crud-form');
        form.action = "{{ route('accounts.store') }}";
        document.getElementById('form-method').value = 'POST';
        form.reset();
        document.getElementById('parent_id').value = '';
        document.getElementById('form-title').innerText = 'إضافة حساب جديد';
        
        const btn = document.getElementById('submit-btn');
        btn.innerText = 'حفظ الحساب';
        btn.className = 'w-full py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 shadow-md transition';
        
        document.getElementById('cancel-btn').classList.add('hidden');
    }
</script>
@endsection