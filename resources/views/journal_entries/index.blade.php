@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto">@include('partials.subnav', ['set' => 'accounts'])</div>
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
                    @foreach($organizations as $org)
                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">رقم القيد <span class="text-slate-400 font-normal">(اتركه فارغاً للترقيم التلقائي)</span></label>
                <input type="text" id="entry_number" name="entry_number" class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs font-mono focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">التاريخ</label>
                <input type="date" id="entry_date" name="entry_date" required value="{{ date('Y-m-d') }}" class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">البيان / الوصف</label>
                <textarea id="description" name="description" rows="3" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>
            </div>
            
            <!-- سطور القيد: يجب أن يتساوى إجمالي المدين مع إجمالي الدائن -->
            <div id="lines-section" class="space-y-2">
                <label class="block text-xs font-bold text-slate-600">سطور القيد (المدين = الدائن)</label>
                <div id="lines-container" class="space-y-2"></div>
                <button type="button" onclick="addLine()" class="w-full py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200 transition">+ إضافة سطر</button>
                <div class="flex justify-between text-xs bg-slate-50 p-2 rounded-xl">
                    <span>مدين: <b id="total-debit">0.00</b></span>
                    <span>دائن: <b id="total-credit">0.00</b></span>
                    <span id="balance-status" class="font-bold text-rose-600">غير متوازن</span>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="submit" id="submit-btn" class="w-full py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 shadow-md transition">حفظ القيد</button>
                <button type="button" id="cancel-btn" onclick="resetForm()" class="hidden w-full py-2.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200 transition">إلغاء</button>
            </div>
        </form>
    </div>

    <!-- الجدول -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden h-fit">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center gap-3 flex-wrap">
            <div>
                <h3 class="text-base font-black text-slate-800">قيود اليومية المحاسبية</h3>
                <p class="text-xs text-slate-400 mt-1">القيود لا تُحذف. القيد الخاطئ يُلغى بـ«قيد عكسي» ثم يُسجَّل الصحيح. قيود النظام (بيع، شراء، مصروف...) لا تُعدَّل من هنا.</p>
            </div>
            <span class="text-xs text-slate-400 font-semibold">الإجمالي: {{ $entries->total() }}</span>
        </div>
        @if (session('success'))
            <div class="mx-6 mt-4 p-3 bg-emerald-50 text-emerald-700 rounded-xl text-xs font-semibold">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mx-6 mt-4 p-3 bg-rose-50 text-rose-600 rounded-xl text-xs font-semibold">{{ session('error') }}</div>
        @endif
        <table class="w-full text-right border-collapse text-xs">
            <thead>
                <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                    <th class="p-4 font-bold">رقم القيد</th>
                    <th class="p-4 font-bold">التاريخ</th>
                    <th class="p-4 font-bold">البيان</th>
                    <th class="p-4 font-bold">المصدر</th>
                    <th class="p-4 font-bold">المبلغ</th>
                    <th class="p-4 font-bold text-center">التحكم</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($entries as $entry)
                @php
                    $payload = [
                        'id' => $entry->id, 'number' => $entry->entry_number, 'date' => (string) $entry->entry_date,
                        'desc' => $entry->description, 'org' => $entry->organization->name ?? 'غير محددة', 'org_id' => $entry->organization_id,
                        'lines' => $entry->lines->map(fn ($l) => ['account' => trim(($l->account->account_code ?? '') . ' ' . ($l->account->name ?? '—')), 'debit' => (float) $l->debit, 'credit' => (float) $l->credit])->values(),
                    ];
                    $isReversed = isset($reversed[$entry->id]);
                @endphp
                <tr class="hover:bg-slate-50/50 {{ $isReversed ? 'opacity-60' : '' }}">
                    <td class="p-4 font-mono font-bold text-indigo-600">{{ $entry->entry_number }}</td>
                    <td class="p-4 text-slate-600">{{ $entry->entry_date }}</td>
                    <td class="p-4 text-slate-800 font-medium">
                        {{ $entry->description }}
                        @if ($isReversed)<div class="text-rose-600 font-bold mt-1">معكوس بالقيد {{ $reversed[$entry->id] }}</div>@endif
                    </td>
                    <td class="p-4"><span class="px-2 py-0.5 rounded-lg {{ $entry->ref_type ? 'bg-slate-100 text-slate-600' : 'bg-indigo-50 text-indigo-600' }}">{{ $entry->ref_type ? ($sources[$entry->ref_type] ?? $entry->ref_type) : 'يدوي' }}</span></td>
                    <td class="p-4 font-mono">{{ number_format($entry->lines->sum('debit'), 2) }}</td>
                    <td class="p-4 text-center whitespace-nowrap">
                        <button type="button" data-entry='@json($payload)' onclick="showDetails(JSON.parse(this.dataset.entry))" class="text-emerald-600 hover:underline font-bold">التفاصيل</button>
                        @if (!$entry->ref_type)
                            <button type="button" data-entry='@json($payload)' onclick="editItem(JSON.parse(this.dataset.entry))" class="text-indigo-600 hover:underline font-bold mr-2">تعديل البيان</button>
                        @endif
                        @if (!$isReversed && $entry->ref_type !== 'reversal')
                            <form action="{{ route('journal-entries.reverse', $entry->id) }}" method="POST" class="inline-block mr-2" onsubmit="return confirm('سيُسجَّل قيد عكسي يلغي أثر هذا القيد، ويبقى الاثنان في السجل. متابعة؟')">
                                @csrf
                                <button type="submit" class="text-rose-600 hover:underline font-bold">قيد عكسي</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="p-6 text-center text-slate-400">لا توجد قيود مسجلة.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $entries->links() }}</div>
    </div>
</div>

<!-- نافذة عرض التفاصيل -->
<div id="details-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-xl w-full p-6 space-y-4">
        <div class="flex justify-between items-center border-b pb-3">
            <h3 class="text-base font-black text-slate-800">القيد <span id="modal-number" class="font-mono text-indigo-600"></span></h3>
            <button onclick="closeDetails()" class="text-slate-400 hover:text-slate-600 font-bold text-sm">✕</button>
        </div>
        <div class="text-xs text-slate-600 space-y-1">
            <div><b>التاريخ:</b> <span id="modal-date"></span> · <b>الصيدلية:</b> <span id="modal-org"></span></div>
            <div><b>البيان:</b> <span id="modal-desc" class="text-slate-800"></span></div>
        </div>
        <table class="w-full text-xs border border-slate-200 rounded-xl overflow-hidden">
            <thead class="bg-slate-50 text-slate-500"><tr><th class="p-2 text-right">الحساب</th><th class="p-2">مدين</th><th class="p-2">دائن</th></tr></thead>
            <tbody id="modal-lines" class="divide-y divide-slate-100"></tbody>
        </table>
        <div class="pt-2 flex justify-end">
            <button onclick="closeDetails()" class="px-5 py-2 bg-slate-200 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-300 transition">إغلاق</button>
        </div>
    </div>
</div>

<script>
    const accountOptions = `@foreach($accountsList as $acc)<option value="{{ $acc->id }}">{{ $acc->account_code }} - {{ $acc->name }}</option>@endforeach`;
    let lineIndex = 0;

    function addLine() {
        const container = document.getElementById('lines-container');
        const row = document.createElement('div');
        row.className = 'grid grid-cols-12 gap-1 items-center';
        row.innerHTML = `
            <select name="lines[${lineIndex}][account_id]" required class="col-span-6 px-2 py-2 rounded-xl border border-slate-200 text-xs bg-white">
                <option value="">-- الحساب --</option>${accountOptions}
            </select>
            <input type="number" step="0.01" min="0" value="0" name="lines[${lineIndex}][debit]" oninput="updateTotals()" placeholder="مدين" class="line-debit col-span-2 px-2 py-2 rounded-xl border border-slate-200 text-xs">
            <input type="number" step="0.01" min="0" value="0" name="lines[${lineIndex}][credit]" oninput="updateTotals()" placeholder="دائن" class="line-credit col-span-3 px-2 py-2 rounded-xl border border-slate-200 text-xs">
            <button type="button" onclick="this.parentElement.remove(); updateTotals();" class="col-span-1 text-rose-600 font-bold">✕</button>`;
        container.appendChild(row);
        lineIndex++;
    }

    function updateTotals() {
        let debit = 0, credit = 0;
        document.querySelectorAll('.line-debit').forEach(i => debit += parseFloat(i.value) || 0);
        document.querySelectorAll('.line-credit').forEach(i => credit += parseFloat(i.value) || 0);
        document.getElementById('total-debit').innerText = debit.toFixed(2);
        document.getElementById('total-credit').innerText = credit.toFixed(2);
        const status = document.getElementById('balance-status');
        const balanced = debit > 0 && Math.abs(debit - credit) < 0.005;
        status.innerText = balanced ? 'متوازن ✓' : 'غير متوازن';
        status.className = 'font-bold ' + (balanced ? 'text-emerald-600' : 'text-rose-600');
    }

    function toggleLines(show) {
        const section = document.getElementById('lines-section');
        section.classList.toggle('hidden', !show);
        section.querySelectorAll('input, select').forEach(el => el.disabled = !show);
    }

    document.addEventListener('DOMContentLoaded', () => { addLine(); addLine(); });

    function showDetails(e) {
        document.getElementById('modal-number').innerText = e.number;
        document.getElementById('modal-date').innerText = e.date;
        document.getElementById('modal-desc').innerText = e.desc;
        document.getElementById('modal-org').innerText = e.org;
        const body = document.getElementById('modal-lines');
        body.innerHTML = '';
        e.lines.forEach(l => {
            const tr = document.createElement('tr');
            [l.account, l.debit ? l.debit.toFixed(2) : '—', l.credit ? l.credit.toFixed(2) : '—'].forEach((v, i) => {
                const td = document.createElement('td');
                td.className = 'p-2 ' + (i ? 'text-center font-mono' : '');
                td.innerText = v;
                tr.appendChild(td);
            });
            body.appendChild(tr);
        });
        const modal = document.getElementById('details-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeDetails() {
        const modal = document.getElementById('details-modal');
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }

    // تعديل البيان والتاريخ فقط (للقيود اليدوية)
    function editItem(e) {
        const form = document.getElementById('crud-form');
        form.action = `{{ url('journal-entries') }}/${e.id}`;
        document.getElementById('form-method').value = 'PUT';
        document.getElementById('entry_number').value = e.number;
        document.getElementById('entry_number').disabled = true;
        document.getElementById('entry_date').value = e.date;
        document.getElementById('description').value = e.desc;
        document.querySelector('select[name="organization_id"]').disabled = true;

        toggleLines(false);
        document.getElementById('form-title').innerText = 'تعديل بيان القيد ' + e.number;
        const btn = document.getElementById('submit-btn');
        btn.innerText = 'تحديث البيان';
        btn.className = 'w-full py-2.5 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 shadow-md transition';
        document.getElementById('cancel-btn').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        const form = document.getElementById('crud-form');
        form.action = "{{ route('journal-entries.store') }}";
        document.getElementById('form-method').value = 'POST';
        form.reset();
        document.getElementById('entry_number').disabled = false;
        document.querySelector('select[name="organization_id"]').disabled = false;
        toggleLines(true);
        updateTotals();
        document.getElementById('form-title').innerText = 'إنشاء قيد يومي جديد';
        const btn = document.getElementById('submit-btn');
        btn.innerText = 'حفظ القيد';
        btn.className = 'w-full py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 shadow-md transition';
        document.getElementById('cancel-btn').classList.add('hidden');
    }
</script>
@endsection
