@extends('layouts.app')

@section('content')
@php
    $decode = function ($value) {
        if (is_array($value)) return $value;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            // دعم السجلات القديمة التي تم ترميزها مرتين
            if (is_string($decoded)) $decoded = json_decode($decoded, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    };
    $old = $decode($auditLog->getRawOriginal('old_values'));
    $new = $decode($auditLog->getRawOriginal('new_values'));
    $keys = collect(array_keys($old))->merge(array_keys($new))->unique()->reject(fn ($k) => in_array($k, ['password', 'remember_token']));
@endphp
<div class="max-w-5xl mx-auto space-y-6">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex justify-between items-start">
        <div class="space-y-1 text-xs text-slate-600">
            <h3 class="text-base font-black text-slate-800">تفاصيل سجل التدقيق #{{ $auditLog->id }}</h3>
            <p>العملية: <b>{{ $auditLog->action }}</b> · الجدول: <b class="font-mono">{{ $auditLog->table_name }}</b> · رقم السجل: <b>{{ $auditLog->record_id }}</b></p>
            <p>المستخدم: <b>{{ $auditLog->user->username ?? 'النظام' }}</b> · IP: <span class="font-mono">{{ $auditLog->ip_address }}</span> · {{ $auditLog->created_at }}</p>
        </div>
        <a href="{{ route('audit-logs.index') }}" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-200">رجوع</a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-right text-xs">
            <thead>
                <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                    <th class="p-3 font-bold">الحقل</th>
                    <th class="p-3 font-bold">القيمة القديمة</th>
                    <th class="p-3 font-bold">القيمة الجديدة</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($keys as $key)
                    @php
                        $o = $old[$key] ?? null;
                        $n = $new[$key] ?? null;
                        $fmt = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v;
                    @endphp
                    <tr class="{{ array_key_exists($key, $new) && $fmt($o) !== $fmt($n) ? 'bg-amber-50/50' : '' }}">
                        <td class="p-3 font-mono font-bold text-slate-700">{{ $key }}</td>
                        <td class="p-3 text-rose-700 break-all">{{ $fmt($o) }}</td>
                        <td class="p-3 text-emerald-700 break-all">{{ $fmt($n) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="p-6 text-center text-slate-400">لا توجد قيم مسجلة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
