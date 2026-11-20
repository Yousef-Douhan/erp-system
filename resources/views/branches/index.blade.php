@extends('layouts.app')

@section('header')
    <div>
        <h2 class="text-xl font-extrabold text-slate-800 tracking-tight">إدارة الفروع</h2>
        <p class="text-xs text-slate-400 mt-0.5">إدارة فروع الصيدليات والصيدليات والمستودعات التابعة للنظام.</p>
    </div>
@endsection

@section('content')
<div class="max-w-7xl mx-auto">@include('partials.subnav', ['set' => 'org'])</div>
@include('partials.flash', ['withSuccess' => false])
    <div class="space-y-6">
        <!-- رسالة النجاح -->
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ session('success') }}
            </div>
        @endif

        <!-- شريط البحث وزر الإضافة (تم نقل الزر هنا لضمان ظهوره فوراً) -->
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="relative w-full sm:w-80">
                <span class="absolute inset-y-0 right-0 flex items-center pr-3.5 pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </span>
                <input type="text" id="branchSearch" onkeyup="searchBranches()" class="w-full pr-10 pl-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition-all" placeholder="بحث عن فرع، صيدلية، أو عنوان...">
            </div>
            
            <div class="flex items-center justify-between w-full sm:w-auto gap-4">
                <div class="text-xs text-slate-400 font-medium">
                    إجمالي الفروع: <span class="font-bold text-slate-700">{{ $branches->total() }}</span>
                </div>
                
                <button onclick="openCreateModal()" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm shadow-indigo-200 transition-all">
                    <svg class="w-4 h-4 text-white flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    إضافة فرع جديد
                </button>
            </div>
        </div>

        <!-- جدول الفروع -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-right border-collapse" id="branchesTable">
                    <thead>
                        <tr class="bg-slate-50/75 border-b border-slate-100 text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">
                            <th class="py-3.5 px-6">اسم الفرع</th>
                            <th class="py-3.5 px-6">الصيدلية التابع لها</th>
                            <th class="py-3.5 px-6">النوع / التصنيف</th>
                            <th class="py-3.5 px-6">الهاتف</th>
                            <th class="py-3.5 px-6">العنوان</th>
                            <th class="py-3.5 px-6">الحالة</th>
                            <th class="py-3.5 px-6 text-left">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-600">
                        @forelse($branches as $branch)
                            <tr class="branch-row hover:bg-slate-50/55 transition-colors">
                                <td class="py-4 px-6 font-bold text-slate-800 branch-name">{{ $branch->name }}</td>
                                <td class="py-4 px-6 branch-org">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-bold text-[11px]">
                                        {{ $branch->organization->name ?? 'غير محدد' }}
                                    </span>
                                </td>
                                <td class="py-4 px-6 branch-type">{{ $branch->type ?? '-' }}</td>
                                <td class="py-4 px-6 font-mono text-slate-500" dir="ltr">{{ $branch->phone ?? '-' }}</td>
                                <td class="py-4 px-6 branch-address">{{ $branch->address ?? '-' }}</td>
                                <td class="py-4 px-6">
                                    @if($branch->status == 'active')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-[11px]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            نشط
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 font-bold text-[11px]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            معطل
                                        </span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-left">
                                    <div class="inline-flex items-center gap-1.5">
                                        <!-- زر التعديل -->
                                        <button onclick='openEditModal(@json($branch))' class="p-1.5 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 transition-colors" title="تعديل">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <!-- زر الحذف -->
                                        <form action="{{ route('branches.destroy', $branch->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا الفرع؟');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 transition-colors" title="حذف">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400 font-medium">لا توجد أي فروع مضافة حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
<div class="p-4">{{ $branches->links() }}</div>
            </div>
        </div>
    </div>

    <!-- Modal الإنشاء والتعديل -->
    <div id="branchModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm hidden">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-xl w-full max-w-lg mx-4 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <h3 id="modalTitle" class="text-sm font-extrabold text-slate-800">إضافة فرع جديد</h3>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            
            <form id="branchForm" method="POST" action="{{ route('branches.store') }}" class="p-6 space-y-4">
                @csrf
                <div id="methodField"></div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الصيدلية التابع لها</label>
                    <select name="organization_id" id="organization_id" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">اختر الصيدلية...</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">اسم الفرع</label>
                    <input type="text" name="name" id="name" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="أدخل اسم الفرع أو المستودع">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">النوع / التصنيف</label>
                        <input type="text" name="type" id="type" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="مثال: صيدلية، مستودع فرعي...">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">رقم الهاتف</label>
                        <input type="text" name="phone" id="phone" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="059xxxxxxxx">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">العنوان</label>
                    <input type="text" name="address" id="address" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="المدينة، الشارع...">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الحالة</label>
                    <select name="status" id="status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="active">نشط</option>
                        <option value="inactive">معطل</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs transition-colors">إلغاء</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-all">حفظ البيانات</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('branchModal');
        const form = document.getElementById('branchForm');
        const modalTitle = document.getElementById('modalTitle');
        const methodField = document.getElementById('methodField');

        function openCreateModal() {
            modalTitle.innerText = 'إضافة فرع جديد';
            form.action = "{{ route('branches.store') }}";
            methodField.innerHTML = '';
            form.reset();
            modal.classList.remove('hidden');
        }

        function openEditModal(branch) {
            modalTitle.innerText = 'تعديل بيانات الفرع';
            form.action = `{{ url('branches') }}/${branch.id}`;
            methodField.innerHTML = '@method("PUT")';
            
            document.getElementById('organization_id').value = branch.organization_id;
            document.getElementById('name').value = branch.name;
            document.getElementById('type').value = branch.type || '';
            document.getElementById('phone').value = branch.phone || '';
            document.getElementById('address').value = branch.address || '';
            document.getElementById('status').value = branch.status;

            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
        }

        // دالة البحث الفوري في الجدول
        function searchBranches() {
            let input = document.getElementById('branchSearch').value.toLowerCase();
            let rows = document.getElementsByClassName('branch-row');

            for (let i = 0; i < rows.length; i++) {
                let name = rows[i].querySelector('.branch-name').innerText.toLowerCase();
                let org = rows[i].querySelector('.branch-org').innerText.toLowerCase();
                let type = rows[i].querySelector('.branch-type').innerText.toLowerCase();
                let address = rows[i].querySelector('.branch-address').innerText.toLowerCase();

                if (name.includes(input) || org.includes(input) || type.includes(input) || address.includes(input)) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }
            }
        }
    </script>
@endsection