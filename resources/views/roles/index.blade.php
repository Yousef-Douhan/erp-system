@extends('layouts.app')

@section('title', 'الأدوار والصلاحيات')

@section('content')
@include('roles._styles')
@php
    $adminName = \App\Support\PermissionCatalog::ADMIN_ROLE;
    $check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5 10 17l9-10"/></svg>';
@endphp

<div class="rp">
    <div class="rp-head">
        <div>
            <h2>الأدوار والصلاحيات</h2>
            <p>كل مستخدم يأخذ دوراً، والدور يحدد الأقسام التي يستطيع فتحها والعمليات التي يستطيع تنفيذها.</p>
        </div>
        <a href="{{ route('roles.create') }}" class="rp-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            دور جديد
        </a>
    </div>

    @if (session('success'))
        <div class="rp-flash ok" role="status">{{ session('success') }}</div>
    @endif

    {{-- ================= بطاقات الأدوار ================= --}}
    <div class="rp-roles">
        @forelse ($roles as $role)
            @php
                $isAdmin = $role->name === $adminName;
                $names = $role->permissions->pluck('name');
                $count = $isAdmin ? $totalPermissions : $names->count();
                $percent = $totalPermissions ? round($count / $totalPermissions * 100) : 0;
                $areas = collect($groups)->map(function ($g) use ($names, $isAdmin) {
                    $all = collect($g['permissions'])->pluck('name');
                    $has = $isAdmin ? $all->count() : $all->intersect($names)->count();
                    return ['title' => $g['title'], 'has' => $has, 'all' => $all->count()];
                })->filter(fn ($a) => $a['has'] > 0);
            @endphp
            <article class="erp-card rp-role {{ $isAdmin ? 'is-admin' : '' }}">
                <div class="rp-role-top">
                    <div>
                        <h3 class="rp-role-name">{{ $role->name }}</h3>
                        <div class="rp-role-users">
                            @if ($role->users_count)
                                <span class="num">{{ $role->users_count }}</span> {{ $role->users_count === 1 ? 'مستخدم' : 'مستخدمين' }}
                            @else
                                لا يوجد مستخدمون بهذا الدور
                            @endif
                        </div>
                    </div>
                    @if ($isAdmin)
                        <span class="rp-lock" title="دور محمي">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4"/></svg>
                            كل الصلاحيات
                        </span>
                    @endif
                </div>

                <div>
                    <div class="rp-meter-label"><span>الصلاحيات</span><b class="num">{{ $count }} من {{ $totalPermissions }}</b></div>
                    <div class="rp-meter"><i style="width: {{ $percent }}%"></i></div>
                </div>

                <div class="rp-areas">
                    @forelse ($areas as $area)
                        <span class="rp-area {{ $area['has'] < $area['all'] ? 'is-partial' : '' }}" title="{{ $area['has'] }} من {{ $area['all'] }}">{{ $area['title'] }}</span>
                    @empty
                        <span class="rp-none">لا يملك أي صلاحية بعد.</span>
                    @endforelse
                </div>

                <div class="rp-role-actions">
                    <a href="{{ route('roles.edit', $role) }}">{{ $isAdmin ? 'عرض' : 'تعديل الصلاحيات' }}</a>
                    @unless ($isAdmin)
                        <form action="{{ route('roles.destroy', $role) }}" method="POST"
                              onsubmit="return confirm('حذف الدور «{{ $role->name }}»؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" {{ $role->users_count ? 'disabled' : '' }}
                                    title="{{ $role->users_count ? 'لا يمكن حذف دور مُسنَد لمستخدمين' : 'حذف الدور' }}">حذف</button>
                        </form>
                    @endunless
                </div>
            </article>
        @empty
            <div class="erp-card erp-empty" style="grid-column: 1 / -1">لا توجد أدوار بعد. أنشئ أول دور وحدد صلاحياته.</div>
        @endforelse
    </div>

    {{-- ================= مصفوفة الصلاحيات ================= --}}
    @if ($roles->isNotEmpty())
        <section class="erp-card">
            <div class="erp-card-head">
                <h2>مصفوفة الصلاحيات</h2>
                <span style="font-size:13px;color:var(--muted)">مقارنة سريعة بين الأدوار</span>
            </div>
            <div class="rp-matrix-wrap">
                <table class="rp-matrix">
                    <thead>
                        <tr>
                            <th>الصلاحية</th>
                            @foreach ($roles as $role)
                                <th>{{ $role->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $group)
                            <tr class="rp-group-row"><td colspan="{{ $roles->count() + 1 }}">{{ $group['title'] }}</td></tr>
                            @foreach ($group['permissions'] as $perm)
                                <tr>
                                    <td class="rp-perm">{{ $perm['label'] }}@if ($perm['hint'])<small>{{ $perm['hint'] }}</small>@endif</td>
                                    @foreach ($roles as $role)
                                        <td>
                                            @if ($role->name === $adminName || $role->permissions->contains('name', $perm['name']))
                                                <span class="rp-yes" aria-label="نعم">{!! $check !!}</span>
                                            @else
                                                <span class="rp-no" aria-label="لا">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
