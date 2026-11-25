@extends('layouts.app')

@section('content')
@include('partials.flash', ['withSuccess' => false])
    <div class="max-w-7xl mx-auto space-y-6">

        @if (session('success'))
            <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl text-xs font-bold">
                {{ session('success') }}
            </div>
        @endif

        <!-- جدول عرض سجلات التدقيق -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                <div>
                    <h3 class="text-base font-black text-slate-800">سجلات التدقيق والمراقبة (Audit Logs)</h3>
                    <p class="text-xs text-slate-400 mt-1">تتبع كافة العمليات والأنشطة التي تمت على النظام</p>
                </div>
                <span class="text-xs text-slate-400 font-semibold">الإجمالي: {{ $logs->total() }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                            <th class="p-4 font-bold">المستخدم</th>
                            <th class="p-4 font-bold">الإجراء (Action)</th>
                            <th class="p-4 font-bold">الجدول</th>
                            <th class="p-4 font-bold">رقم السجل</th>
                            <th class="p-4 font-bold">عنوان الـ IP</th>
                            <th class="p-4 font-bold">التاريخ والوقت</th>
                            <th class="p-4 font-bold text-center">التحكم</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($logs as $log)
                            <tr class="hover:bg-slate-50/50">
                                <td class="p-4 font-bold text-slate-800">{{ $log->user->username ?? 'مستخدم مجهول / نظام' }}
                                </td>

                                <td class="p-4">
                                    @php
                                        $badgeClass = match ($log->action) {
                                            'created' => 'bg-emerald-50 text-emerald-600',
                                            'updated' => 'bg-amber-50 text-amber-600',
                                            'deleted' => 'bg-rose-50 text-rose-600',
                                            default => 'bg-indigo-50 text-indigo-600',
                                        };
                                    @endphp
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $badgeClass }}">
                                        {{ $log->action }}
                                    </span>
                                </td>

                                <td class="p-4 font-mono text-slate-600">{{ $log->table_name }}</td>
                                <td class="p-4 text-slate-600">#{{ $log->record_id ?? '---' }}</td>
                                <td class="p-4 font-mono text-slate-500">{{ $log->ip_address ?? '---' }}</td>
                                <td class="p-4 text-slate-500">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>

                                <td class="p-4 text-center space-x-2 space-x-reverse">
                                    <!-- زر عرض التفاصيل بشكل أنيق ككروت -->
                                    <button type="button"
                                        data-log="{{ json_encode([
                                            'user' => $log->user->username ?? 'غير معروف',
                                            'action' => $log->action,
                                            'table' => $log->table_name,
                                            'ip' => $log->ip_address,
                                            'old' => $log->old_values,
                                            'new' => $log->new_values,
                                        ], JSON_UNESCAPED_UNICODE) }}"
                                        onclick="showDetails(JSON.parse(this.dataset.log))"
                                        class="text-indigo-600 hover:underline font-bold">التفاصيل</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-6 text-center text-slate-400">لا توجد سجلات تدقيق مسجلة حتى
                                    الآن.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- نافذة منبثقة (Modal) بتصميم أنيق ومبسط لعرض التفاصيل -->
    <div id="show-modal"
        class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div
            class="bg-white rounded-3xl p-6 max-w-2xl w-full space-y-6 text-xs shadow-2xl max-h-[90vh] overflow-y-auto border border-slate-100">

            <!-- رأس النافذة -->
            <div class="flex justify-between items-center border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-black text-slate-800">تفاصيل السجل</h3>
                    <p class="text-slate-400 text-[11px] mt-0.5">معلومات تفصيلية حول العملية التي تمت على النظام</p>
                </div>
                <button type="button" onclick="closeModal()"
                    class="w-8 h-8 rounded-full bg-slate-50 text-slate-400 hover:bg-slate-100 hover:text-slate-600 flex items-center justify-center font-bold text-sm transition">✕</button>
            </div>

            <!-- معلومات أساسية -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 bg-slate-50/80 p-4 rounded-2xl border border-slate-100">
                <div>
                    <span class="block text-slate-400 text-[10px] mb-1">المستخدم</span>
                    <strong id="modal-user" class="text-slate-800 text-xs"></strong>
                </div>
                <div>
                    <span class="block text-slate-400 text-[10px] mb-1">الإجراء</span>
                    <strong id="modal-action" class="text-slate-800 text-xs uppercase"></strong>
                </div>
                <div>
                    <span class="block text-slate-400 text-[10px] mb-1">الجدول</span>
                    <strong id="modal-table" class="text-slate-800 font-mono text-xs"></strong>
                </div>
                <div>
                    <span class="block text-slate-400 text-[10px] mb-1">عنوان IP</span>
                    <strong id="modal-ip" class="text-slate-800 font-mono text-xs"></strong>
                </div>
            </div>

            <!-- عرض البيانات بتنسيق كروت منسقة وأنيقة -->
            <div class="space-y-4">
                <div id="old-values-container" class="hidden">
                    <h4 class="font-bold text-slate-700 mb-2 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span> القيم القديمة:
                    </h4>
                    <div id="modal-old-content"
                        class="bg-slate-50 rounded-2xl border border-slate-100 p-4 space-y-2 max-h-56 overflow-y-auto">
                    </div>
                </div>

                <div id="new-values-container" class="hidden">
                    <h4 class="font-bold text-slate-700 mb-2 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> القيم الجديدة / المسجلة:
                    </h4>
                    <div id="modal-new-content"
                        class="bg-slate-50 rounded-2xl border border-slate-100 p-4 space-y-2 max-h-56 overflow-y-auto">
                    </div>
                </div>
            </div>

            <!-- زر الإغلاق -->
            <div class="text-left pt-2 border-t border-slate-100">
                <button type="button" onclick="closeModal()"
                    class="px-5 py-2.5 bg-slate-900 text-white rounded-xl font-bold hover:bg-slate-800 transition text-xs shadow-sm">إغلاق
                    النافذة</button>
            </div>
        </div>
    </div>

    <script>
        // القيم المسجلة قد تحتوي نصوصاً أدخلها المستخدمون، فتُهرَّب قبل وضعها في الصفحة
        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value === null || value === undefined ? '' : String(value);
            return div.innerHTML;
        }

        function showDetails(log) {
            document.getElementById('modal-user').innerText = log.user ?? '';
            document.getElementById('modal-action').innerText = log.action ?? '';
            document.getElementById('modal-table').innerText = log.table ?? '';
            document.getElementById('modal-ip').innerText = log.ip ?? '';

            // عرض القيم القديمة بشكل أنيق
            renderJsonData(log.old, 'old-values-container', 'modal-old-content');

            // عرض القيم الجديدة بشكل أنيق
            renderJsonData(log.new, 'new-values-container', 'modal-new-content');

            document.getElementById('show-modal').classList.remove('hidden');
        }

        // دالة لتحويل الـ JSON إلى كروت منسقة ومرتبة
        function renderJsonData(rawVal, containerId, contentId) {
            const container = document.getElementById(containerId);
            const contentDiv = document.getElementById(contentId);

            if (!rawVal || rawVal === 'null' || rawVal === '[]' || rawVal === '{}') {
                container.classList.add('hidden');
                return;
            }

            try {
                let data = typeof rawVal === 'object' ? rawVal : JSON.parse(rawVal);

                if (!data || Object.keys(data).length === 0) {
                    container.classList.add('hidden');
                    return;
                }

                container.classList.remove('hidden');
                let html = '<div class="grid grid-cols-1 sm:grid-cols-2 gap-2">';

                for (const [key, value] of Object.entries(data)) {
                    let displayVal = (typeof value === 'object' && value !== null) ? JSON.stringify(value) : value;
                    html += `
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/60 flex flex-col justify-between">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">${escapeHtml(key)}</span>
                        <span class="text-slate-800 font-semibold text-xs mt-0.5 break-all">${escapeHtml(displayVal ?? '---')}</span>
                    </div>
                `;
                }
                html += '</div>';
                contentDiv.innerHTML = html;
            } catch (e) {
                container.classList.remove('hidden');
                contentDiv.innerHTML = `<p class="text-slate-600 text-xs">${escapeHtml(rawVal)}</p>`;
            }
        }

        function closeModal() {
            document.getElementById('show-modal').classList.add('hidden');
        }
    </script>
@endsection
