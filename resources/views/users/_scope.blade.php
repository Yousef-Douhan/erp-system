{{--
    حقول الصيدلية والفرع للمستخدم.
    $idPrefix: بادئة المعرّفات (مثل 'edit_')، $org / $branch: القيم المختارة
    مدير النظام يختار الصيدلية والفرع، صاحب الصيدلية يختار الفرع، مدير الفرع لا يختار شيئاً (فرعه تلقائياً).
--}}
@php
    $idPrefix = $idPrefix ?? '';
    $isSuper = \App\Support\Tenancy::isSuperAdmin();
    $isBranchUser = (bool) \App\Support\Tenancy::branchId();
    $fieldClass = 'w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none';
@endphp

@if ($isSuper)
    <div>
        <label class="block text-xs font-bold text-slate-700 mb-1" for="{{ $idPrefix }}organization_id">الصيدلية</label>
        <select id="{{ $idPrefix }}organization_id" name="organization_id" class="{{ $fieldClass }}" data-scope-org="{{ $idPrefix }}">
            <option value="">بدون صيدلية (لمدير النظام فقط)</option>
            @foreach ($organizations as $org)
                <option value="{{ $org->id }}" @selected((int) old('organization_id', $orgValue ?? null) === $org->id)>{{ $org->name }}</option>
            @endforeach
        </select>
    </div>
@endif

@unless ($isBranchUser)
    <div>
        <label class="block text-xs font-bold text-slate-700 mb-1" for="{{ $idPrefix }}branch_id">الفرع</label>
        <select id="{{ $idPrefix }}branch_id" name="branch_id" class="{{ $fieldClass }}" data-scope-branch="{{ $idPrefix }}">
            <option value="">كل الفروع (على مستوى الصيدلية)</option>
            @foreach ($branches as $b)
                <option value="{{ $b->id }}" data-org="{{ $b->organization_id }}" @selected((int) old('branch_id', $branchValue ?? null) === $b->id)>
                    {{ $isSuper ? (($b->organization->name ?? '') . ' — ') : '' }}{{ $b->name }}
                </option>
            @endforeach
        </select>
        <p class="text-[11px] text-slate-500 mt-1">الكاشير ومدير الفرع: اختر فرعه. صاحب الصيدلية: اتركه «كل الفروع».</p>
    </div>
@endunless

@once
<script>
// عند اختيار صيدلية تظهر فروعها فقط
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-scope-org]').forEach(org => {
        const branch = document.querySelector(`[data-scope-branch="${org.dataset.scopeOrg}"]`);
        if (!branch) return;
        const sync = () => {
            [...branch.options].forEach(o => { if (o.dataset.org) o.hidden = org.value !== '' && o.dataset.org !== org.value; });
            if (branch.selectedOptions[0]?.hidden) branch.value = '';
        };
        org.addEventListener('change', sync);
        sync();
    });
});
</script>
@endonce
