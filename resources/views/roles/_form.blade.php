@php
    $isAdmin = $role && $role->name === \App\Support\PermissionCatalog::ADMIN_ROLE;
    $selected = collect($selected ?? []);
    $total = collect($groups)->sum(fn ($g) => count($g['permissions']));
@endphp

<form action="{{ $role ? route('roles.update', $role) : route('roles.store') }}" method="POST" id="rpForm" class="rp-form">
    @csrf
    @if ($role) @method('PUT') @endif

    {{-- ================= الصلاحيات ================= --}}
    <div style="display:grid;gap:14px;min-width:0">
        @if ($errors->any())
            <div class="rp-flash err" role="alert">
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($isAdmin)
            <div class="rp-admin-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4"/></svg>
                <span>دور مدير النظام محمي: يملك كل الصلاحيات دائماً، ولا يمكن تغيير اسمه أو حذفه، حتى لا يُغلق النظام على نفسه.</span>
            </div>
        @endif

        <div class="rp-tools">
            <label class="rp-search">
                <span class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">ابحث عن صلاحية</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 21l-4.35-4.35M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0z"/></svg>
                <input type="search" id="rpSearch" placeholder="ابحث عن صلاحية، مثل: الفواتير أو المنتجات" autocomplete="off">
            </label>
            @unless ($isAdmin)
                <button type="button" class="rp-link-btn" data-bulk="all">تحديد الكل</button>
                <button type="button" class="rp-link-btn" data-bulk="none">إلغاء الكل</button>
            @endunless
        </div>

        <div class="rp-groups" id="rpGroups">
            @foreach ($groups as $key => $group)
                <section class="erp-card rp-group" data-group="{{ $key }}">
                    <label class="rp-group-head">
                        <span class="rp-group-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $group['icon'] }}"/></svg>
                        </span>
                        <span class="rp-group-title">
                            <strong>{{ $group['title'] }}</strong>
                            <span><span class="rp-group-count num">0</span> من <span class="num">{{ count($group['permissions']) }}</span>، {{ $group['hint'] }}</span>
                        </span>
                        <input type="checkbox" class="rp-group-toggle" aria-label="تحديد كل صلاحيات {{ $group['title'] }}" {{ $isAdmin ? 'checked disabled' : '' }}>
                    </label>

                    @foreach ($group['permissions'] as $perm)
                        <label class="rp-perm-item" data-search="{{ $perm['label'] }} {{ $perm['hint'] }} {{ $perm['name'] }} {{ $group['title'] }}">
                            <span class="rp-perm-text">
                                <strong>{{ $perm['label'] }}</strong>
                                @if ($perm['hint'])<span>{{ $perm['hint'] }}</span>@endif
                                <code>{{ $perm['name'] }}</code>
                            </span>
                            <span class="rp-switch">
                                <input type="checkbox" name="permissions[]" value="{{ $perm['name'] }}"
                                       {{ $isAdmin || $selected->contains($perm['name']) ? 'checked' : '' }}
                                       {{ $isAdmin ? 'disabled' : '' }}>
                                <i aria-hidden="true"></i>
                            </span>
                        </label>
                    @endforeach
                </section>
            @endforeach
        </div>
        <div class="erp-card rp-empty-search" id="rpNoResults" hidden>لا توجد صلاحية مطابقة. جرّب كلمة أخرى.</div>
    </div>

    {{-- ================= بيانات الدور ================= --}}
    <aside class="erp-card rp-side">
        <div class="rp-field">
            <label for="rpName">اسم الدور</label>
            <input type="text" id="rpName" name="name" value="{{ old('name', $role->name ?? '') }}" required maxlength="100"
                   placeholder="مثال: صيدلي، كاشير، محاسب" {{ $isAdmin ? 'readonly' : '' }}>
            <small>الاسم الذي يظهر عند إسناد الدور للمستخدمين.</small>
        </div>

        <div class="rp-count">
            <strong><span id="rpSelected" class="num">0</span> <small>من <span class="num">{{ $total }}</span></small></strong>
            <span>صلاحية محددة</span>
        </div>

        @if ($role && $role->users_count)
            <div style="font-size:13px;color:var(--muted);line-height:1.6">
                التغيير يطبَّق فوراً على <b class="num" style="color:var(--ink)">{{ $role->users_count }}</b> {{ $role->users_count === 1 ? 'مستخدم' : 'مستخدمين' }} بهذا الدور.
            </div>
        @endif

        <div class="rp-side-actions">
            @unless ($isAdmin)
                <button type="submit" class="rp-btn">{{ $role ? 'حفظ التغييرات' : 'إنشاء الدور' }}</button>
            @endunless
            <a href="{{ route('roles.index') }}" class="rp-btn is-ghost">{{ $isAdmin ? 'رجوع' : 'إلغاء' }}</a>
        </div>
    </aside>
</form>

<script>
(function () {
    const groups = [...document.querySelectorAll('.rp-group')];
    const boxes = () => [...document.querySelectorAll('input[name="permissions[]"]')];
    const norm = s => String(s || '').toLowerCase()
        .replace(/[ً-ٰٟـ]/g, '').replace(/[إأآ]/g, 'ا').replace(/ى|ئ/g, 'ي').replace(/ؤ/g, 'و').replace(/ة/g, 'ه').trim();

    function refresh() {
        let total = 0;
        groups.forEach(g => {
            const items = [...g.querySelectorAll('input[name="permissions[]"]')];
            const on = items.filter(i => i.checked).length;
            total += on;
            g.querySelector('.rp-group-count').textContent = on;
            const toggle = g.querySelector('.rp-group-toggle');
            if (!toggle.disabled) {
                toggle.checked = on === items.length && items.length > 0;
                toggle.indeterminate = on > 0 && on < items.length;
            }
        });
        document.getElementById('rpSelected').textContent = total;
    }

    groups.forEach(g => {
        const toggle = g.querySelector('.rp-group-toggle');
        toggle.addEventListener('change', () => {
            g.querySelectorAll('input[name="permissions[]"]').forEach(i => { if (!i.disabled) i.checked = toggle.checked; });
            refresh();
        });
    });
    document.getElementById('rpGroups').addEventListener('change', e => { if (e.target.name === 'permissions[]') refresh(); });

    document.querySelectorAll('[data-bulk]').forEach(b => b.addEventListener('click', () => {
        boxes().forEach(i => { if (!i.disabled) i.checked = b.dataset.bulk === 'all'; });
        refresh();
    }));

    const search = document.getElementById('rpSearch');
    search.addEventListener('input', () => {
        const words = norm(search.value).split(/\s+/).filter(Boolean);
        let any = false;
        groups.forEach(g => {
            let visible = 0;
            g.querySelectorAll('.rp-perm-item').forEach(item => {
                const text = norm(item.dataset.search);
                const show = words.every(w => text.includes(w));
                item.hidden = !show;
                if (show) visible++;
            });
            g.hidden = visible === 0;
            if (visible) any = true;
        });
        document.getElementById('rpNoResults').hidden = any;
    });

    refresh();
})();
</script>
