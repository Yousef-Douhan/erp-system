{{-- تبويبات تربط الصفحات المتقاربة (بدل عنصر لكل صفحة في القائمة الجانبية). الاستخدام: @include('partials.subnav', ['set' => 'catalog']) --}}
@php
    $sets = [
        'catalog' => [
            ['التصنيفات', 'categories.index', 'categories.*', 'view_pharmacy'],
            ['الوحدات', 'units.index', 'units.*', 'view_pharmacy'],
            ['الماركات', 'brands.index', 'brands.*', 'view_pharmacy'],
            ['التنبيهات الدوائية', 'drug-alerts.index', 'drug-alerts.*', 'view_pharmacy'],
        ],
        'org' => [
            ['الفروع', 'branches.index', 'branches.*', 'manage_settings'],
            ['الصيدليات', 'organizations.index', 'organizations.*', 'manage_settings'],
            ['المستودعات', 'warehouses.index', 'warehouses.*', 'transfer_stock'],
            ['طرق الدفع', 'payment-methods.index', 'payment-methods.*', 'manage_settings'],
        ],
        'accounts' => [
            ['دليل الحسابات', 'accounts.index', 'accounts.*', 'manage_accounting'],
            ['القيود اليومية', 'journal-entries.index', 'journal-entries.*', 'manage_accounting'],
            ['قائمة الدخل', 'reports.income-statement', 'reports.income-statement', 'manage_accounting'],
            ['الميزانية العمومية', 'reports.balance-sheet', 'reports.balance-sheet', 'manage_accounting'],
        ],
    ];
    $tabs = collect($sets[$set] ?? [])->filter(fn ($t) => \Illuminate\Support\Facades\Route::has($t[1]) && auth()->user()?->can($t[3]));
@endphp
@if ($tabs->count() > 1)
    <nav class="x-tabs no-print" style="margin-bottom:16px;flex-wrap:wrap">
        @foreach ($tabs as [$label, $route, $active])
            <a href="{{ route($route) }}" class="{{ request()->routeIs($active) ? 'is-active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>
@endif
