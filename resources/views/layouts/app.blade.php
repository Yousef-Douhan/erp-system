<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

@php
    $icons = [
        'home'     => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5z',
        'pos'      => 'M4 4h16v10H4zM8 18h8M12 14v4M7 8h.01M11 8h.01M15 8h2',
        'receipt'  => 'M6 2h12v20l-3-2-3 2-3-2-3 2V2zm3 6h6m-6 4h6m-6 4h4',
        'users2'   => 'M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9.5 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zM21 19v-1a4 4 0 0 0-3-3.87M15.5 3.13a3.5 3.5 0 0 1 0 6.74',
        'debt'     => 'M12 3v18M17 7H9.5a3 3 0 0 0 0 6h5a3 3 0 0 1 0 6H6',
        'pill'     => 'M10.5 20.5 3.5 13.5a5 5 0 0 1 7-7l7 7a5 5 0 0 1-7 7zM8.5 8.5l7 7',
        'tag'      => 'M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9-9-9zM7.5 7.5h.01',
        'award'    => 'M12 15a6 6 0 1 0 0-12 6 6 0 0 0 0 12zM8.2 13.9 7 22l5-3 5 3-1.2-8.1',
        'factory'  => 'M2 20V9l6 4V9l6 4V4h8v16H2zM6 17h2m4 0h2m4 0h2',
        'scale'    => 'M12 3v18M5 7h14M5 7l-3 7a3 3 0 0 0 6 0L5 7zm14 0-3 7a3 3 0 0 0 6 0l-3-7zM8 21h8',
        'layers'   => 'm12 2 10 5-10 5L2 7l10-5zM2 12l10 5 10-5M2 17l10 5 10-5',
        'arrows'   => 'M7 16V4m0 0L3 8m4-4 4 4M17 8v12m0 0 4-4m-4 4-4-4',
        'box-in'   => 'M21 8v12H3V8M1 3h22v5H1zM12 11v6m-3-3 3 3 3-3',
        'sliders'  => 'M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6',
        'rx'       => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM14 2v6h6M9 13h6M12 10v6',
        'alert'    => 'M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4m0 4h.01',
        'cart'     => 'M3 3h2l2.4 12.2a2 2 0 0 0 2 1.8h8.8a2 2 0 0 0 2-1.6L22 7H6M9 21h.01M18 21h.01',
        'truck'    => 'M1 4h13v12H1zM14 9h4l3 3v4h-7M5.5 19.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM17.5 19.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z',
        'book'     => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14zM4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5',
        'journal'  => 'M9 4H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2M9 4a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 12h6m-6 4h4',
        'ledger'   => 'M3 3v18h18M7 15l4-4 3 3 5-6',
        'wallet'   => 'M20 7H5a2 2 0 0 1 0-4h13v4M3 5v14a2 2 0 0 0 2 2h15V7M16 14h.01',
        'safe'     => 'M3 4h18v16H3zM7 20v2m10-2v2M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6zm0 0V7m3 5h2',
        'minus'    => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zM8 12h8',
        'chart'    => 'M3 3v18h18M8 17V9m5 8V5m5 12v-6',
        'balance'  => 'M12 3v18M4 7h16M4 7l-2 6a3 3 0 0 0 4 0L4 7zm16 0-2 6a3 3 0 0 0 4 0l-2-6z',
        'building' => 'M4 21V3h11v18M15 9h5v12M8 7h3m-3 4h3m-3 4h3M2 21h20',
        'store'    => 'M3 9 4.5 3h15L21 9M3 9v12h18V9M3 9h18M9 21v-6h6v6',
        'badge'    => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM19 8v6m3-3h-6',
        'link'     => 'M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1m2 5a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1',
        'card'     => 'M2 5h20v14H2zM2 10h20M6 15h4',
        'user'     => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
        'shield'   => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4',
        'history'  => 'M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 3',
        'bell'     => 'M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0',
        'logout'   => 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9',
        'menu'     => 'M3 6h18M3 12h18M3 18h18',
        'chevron'  => 'm15 18-6-6 6-6',
        'plus'     => 'M12 5v14M5 12h14',
        'undo'     => 'M9 14 4 9l5-5M4 9h11a5 5 0 0 1 0 10h-3',
        'clock'    => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 6v6l4 2',
        'clipboard'=> 'M9 4h6v3H9zM8 5H6v16h12V5h-2M9 12l2 2 4-4',
        'warehouse'=> 'M3 21V9l9-6 9 6v12M7 21v-8h10v8M7 17h10',
        'barcode'  => 'M3 5v14M7 5v14M10 5v14M14 5v14M17 5v14M21 5v14',
        'trend'    => 'm3 17 6-6 4 4 8-8M15 7h6v6',
        'trash'    => 'M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15M10 11v6M14 11v6',
        'database' => 'M12 3c4.4 0 8 1.3 8 3v12c0 1.7-3.6 3-8 3s-8-1.3-8-3V6c0-1.7 3.6-3 8-3zM4 6c0 1.7 3.6 3 8 3s8-1.3 8-3M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3',
    ];

    $route = fn ($name, $params = []) => Route::has($name) ? route($name, $params) : null;

    // مجموعات القائمة: [العنوان, الصلاحيات, [[الاسم, الأيقونة, الراوت, نمط التفعيل], ...]]
    $groups = [
        ['key' => 'sales', 'title' => 'المبيعات', 'can' => ['manage_sales', 'view_customers', 'return_sales', 'manage_shifts', 'view_pharmacy'], 'items' => [
            ['label' => 'الفواتير', 'icon' => 'receipt', 'route' => 'sales.index', 'active' => ['sales.*', 'returns.create'], 'can' => ['manage_sales']],
            ['label' => 'المرتجعات', 'icon' => 'undo', 'route' => 'returns.index', 'active' => ['returns.index', 'returns.show'], 'can' => ['return_sales']],
            ['label' => 'العملاء', 'icon' => 'users2', 'route' => 'customers.index', 'active' => 'customers.index', 'can' => ['manage_sales', 'view_customers']],
            ['label' => 'دفتر الديون', 'icon' => 'debt', 'route' => 'debts.index', 'active' => 'debts.*', 'can' => ['manage_sales', 'view_customers']],
            ['label' => 'الورديات', 'icon' => 'clock', 'route' => 'shifts.index', 'active' => 'shifts.*', 'can' => ['manage_shifts', 'manage_accounting']],
            ['label' => 'الوصفات الطبية', 'icon' => 'rx', 'route' => 'prescriptions.index', 'active' => 'prescriptions.*', 'can' => ['view_pharmacy']],
        ]],
        ['key' => 'pharmacy', 'title' => 'المخزون', 'can' => ['view_pharmacy', 'stock_count', 'transfer_stock', 'print_barcodes'], 'items' => [
            ['label' => 'المنتجات', 'icon' => 'pill', 'route' => 'products.index', 'active' => 'products.*', 'can' => ['view_pharmacy']],
            ['label' => 'الدفعات والصلاحية', 'icon' => 'layers', 'route' => 'batches.index', 'active' => 'batches.*', 'can' => ['view_pharmacy']],
            ['label' => 'الإتلاف', 'icon' => 'alert', 'route' => 'disposals.index', 'active' => 'disposals.*', 'can' => ['stock_count']],
            ['label' => 'الجرد', 'icon' => 'clipboard', 'route' => 'stock-counts.index', 'active' => 'stock-counts.*', 'can' => ['stock_count']],
            ['label' => 'نقل المخزون', 'icon' => 'arrows', 'route' => 'stock-transfers.index', 'active' => 'stock-transfers.*', 'can' => ['transfer_stock']],
            ['label' => 'الحركات المخزنية', 'icon' => 'history', 'route' => 'inventory-transactions.index', 'active' => 'inventory-transactions.*', 'can' => ['view_pharmacy']],
            ['label' => 'ملصقات الباركود', 'icon' => 'barcode', 'route' => 'barcodes.index', 'active' => 'barcodes.*', 'can' => ['print_barcodes']],
            ['label' => 'إعدادات الأصناف', 'icon' => 'tag', 'route' => 'categories.index', 'active' => ['categories.*', 'units.*', 'brands.*', 'drug-alerts.*'], 'can' => ['view_pharmacy']],
        ]],
        ['key' => 'purchases', 'title' => 'المشتريات', 'can' => ['view_purchases'], 'items' => [
            ['label' => 'فواتير الشراء', 'icon' => 'cart', 'route' => 'purchases.index', 'active' => ['purchases.*', 'purchase-returns.create']],
            ['label' => 'اقتراح طلبية', 'icon' => 'clipboard', 'route' => 'reorder.index', 'active' => 'reorder.*'],
            ['label' => 'مرتجعات للموردين', 'icon' => 'undo', 'route' => 'purchase-returns.index', 'active' => ['purchase-returns.index', 'purchase-returns.show']],
            ['label' => 'الموردون', 'icon' => 'truck', 'route' => 'suppliers.index', 'active' => 'suppliers.*'],
            ['label' => 'سداد الموردين', 'icon' => 'wallet', 'route' => 'supplier-payments.index', 'active' => 'supplier-payments.*', 'can' => ['pay_suppliers']],
        ]],
        ['key' => 'accounting', 'title' => 'المالية', 'can' => ['manage_accounting', 'verify_transfers', 'upload_transfer_docs'], 'items' => [
            ['label' => 'مطابقة الحوالات', 'icon' => 'card', 'route' => 'transfers.index', 'active' => 'transfers.*', 'can' => ['verify_transfers', 'upload_transfer_docs']],
            ['label' => 'فواتير بدون إنترنت', 'icon' => 'receipt', 'route' => 'offline-sales.index', 'active' => 'offline-sales.*', 'can' => ['manage_accounting']],
            ['label' => 'الخزائن', 'icon' => 'safe', 'route' => 'cash-registers.index', 'active' => 'cash-registers.*', 'can' => ['manage_accounting']],
            ['label' => 'المصروفات', 'icon' => 'minus', 'route' => 'expenses.index', 'active' => 'expenses.*', 'can' => ['manage_accounting']],
            ['label' => 'الحسابات والقيود', 'icon' => 'book', 'route' => 'accounts.index', 'active' => ['accounts.*', 'journal-entries.*', 'reports.income-statement', 'reports.balance-sheet'], 'can' => ['manage_accounting']],
        ]],
        ['key' => 'reports', 'title' => 'التقارير', 'can' => ['view_reports'], 'items' => [
            ['label' => 'التقارير', 'icon' => 'trend', 'route' => 'reports.sales', 'active' => ['reports.sales', 'reports.profit', 'reports.employees', 'reports.slow']],
        ]],
        ['key' => 'admin', 'title' => 'الإدارة', 'can' => ['manage_settings', 'transfer_stock', 'manage_users', 'manage_roles', 'view_audit_logs', 'manage_backups', 'delete_products', 'delete_customers', 'delete_purchases', 'manage_accounting'], 'items' => [
            ['label' => 'الصيدلية والفروع', 'icon' => 'store', 'route' => 'branches.index', 'active' => ['organizations.*', 'branches.*', 'warehouses.*', 'payment-methods.*'], 'can' => ['manage_settings']],
            ['label' => 'المستخدمون', 'icon' => 'user', 'route' => 'users.index', 'active' => 'users.*', 'can' => ['manage_users']],
            ['label' => 'الأدوار والصلاحيات', 'icon' => 'shield', 'route' => 'roles.index', 'active' => 'roles.*', 'can' => ['manage_roles']],
            ['label' => 'سجل التدقيق', 'icon' => 'history', 'route' => 'audit-logs.index', 'active' => 'audit-logs.*', 'can' => ['view_audit_logs']],
            ['label' => 'النسخ الاحتياطية', 'icon' => 'database', 'route' => 'backups.index', 'active' => 'backups.*', 'can' => ['manage_backups']],
            ['label' => 'سلة المحذوفات', 'icon' => 'trash', 'route' => 'trash.index', 'active' => 'trash.*', 'can' => ['delete_products', 'delete_customers', 'delete_purchases', 'manage_users', 'manage_settings', 'manage_accounting']],
        ]],
    ];

    $user = auth()->user();
    $isActive = fn ($patterns) => request()->routeIs(...(array) $patterns);

    // عنوان الصفحة من عنصر القائمة النشط
    $pageTitle = null;
    $pageGroup = null;
    foreach ($groups as $g) {
        foreach ($g['items'] as $it) {
            if ($isActive($it['active'])) { $pageTitle = $it['label']; $pageGroup = $g['title']; break 2; }
        }
    }
    $pageTitle = trim($__env->yieldContent('title')) ?: ($pageTitle ?? match (true) {
        request()->routeIs('dashboard') => 'الرئيسية',
        request()->routeIs('analytics') => 'التحليلات',
        request()->routeIs('pos.*') => 'نقطة البيع',
        request()->routeIs('notifications.*') => 'الإشعارات',
        request()->routeIs('profile.*') => 'الملف الشخصي',
        default => config('app.name', 'ERP'),
    });

    // عدادات الإطار تُخزَّن مؤقتاً لدقيقة حتى لا تُنفَّذ مع كل صفحة
    $unread = $user
        ? \Illuminate\Support\Facades\Cache::remember('layout.unread.' . $user->id, 60, fn () => $user->unreadNotifications()->count())
        : 0;
    $lowStockCount = $user && $user->can('view_pharmacy')
        ? \Illuminate\Support\Facades\Cache::remember(
            'layout.low_stock.' . (\App\Support\Tenancy::active() ? (\App\Support\Tenancy::branchId() ? 'b' . \App\Support\Tenancy::branchId() : 'o' . \App\Support\Tenancy::orgId()) : 'all'),
            60,
            fn () => \App\Support\Stock::lowStockQuery()->count()
        )
        : 0;
@endphp

    <title>{{ $pageTitle }} — {{ config('app.name', 'ERP') }}</title>
    @include('partials.pwa')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- نظام التصميم الموحد (بعد Tailwind حتى يطغى عليه) --}}
    <link rel="stylesheet" href="{{ asset('css/erp-theme.css') }}?v={{ @filemtime(public_path('css/erp-theme.css')) }}">
</head>

<body class="erp">
    <div class="erp-shell">

        {{-- ================= القائمة الجانبية ================= --}}
        <aside class="erp-sidebar" id="erpSidebar" aria-label="القائمة الرئيسية">
            <a href="{{ $route('dashboard') ?? '#' }}" class="erp-brand" style="text-decoration:none">
                <span class="erp-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                </span>
                <span class="erp-brand-text">
                    <strong>{{ config('app.name') === 'Laravel' ? 'نظام الصيدلية' : config('app.name', 'نظام الصيدلية') }}</strong>
                    <span>إدارة المبيعات والمخزون والحسابات</span>
                </span>
            </a>

            <nav class="erp-nav">
                <a href="{{ $route('dashboard') ?? '#' }}" class="erp-link {{ $isActive('dashboard') ? 'is-active' : '' }}" title="الرئيسية">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons['home'] }}"/></svg>
                    <span>الرئيسية</span>
                </a>

                @can('view_dashboard')
                    <a href="{{ $route('analytics') ?? '#' }}" class="erp-link {{ $isActive('analytics') ? 'is-active' : '' }}" title="التحليلات">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons['chart'] }}"/></svg>
                        <span>التحليلات</span>
                    </a>
                @endcan

                @can('manage_sales')
                    <a href="{{ $route('pos.index') ?? '#' }}" class="erp-link erp-link-primary {{ $isActive('pos.*') ? 'is-active' : '' }}" title="نقطة البيع">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons['pos'] }}"/></svg>
                        <span>نقطة البيع</span>
                    </a>
                @endcan

                @foreach ($groups as $group)
                    @canany($group['can'])
                        @php
                            $visible = collect($group['items'])->filter(fn ($it) => $route($it['route']) && (!isset($it['can']) || $user->canAny($it['can'])));
                            $groupActive = $visible->contains(fn ($it) => $isActive($it['active']));
                        @endphp
                        @if ($visible->isNotEmpty())
                            <div class="erp-group" data-group="{{ $group['key'] }}" data-active="{{ $groupActive ? '1' : '0' }}">
                                <button type="button" class="erp-group-toggle" aria-expanded="true">
                                    <span>{{ $group['title'] }}</span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div class="erp-group-items">
                                    @foreach ($visible as $item)
                                        <a href="{{ $route($item['route']) }}" class="erp-link {{ $isActive($item['active']) ? 'is-active' : '' }}" title="{{ $item['label'] }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons[$item['icon']] }}"/></svg>
                                            <span>{{ $item['label'] }}</span>
                                            @if ($item['route'] === 'products.index' && $lowStockCount > 0)
                                                <em class="erp-badge" style="font-style:normal" title="منتجات وصلت للحد الأدنى">{{ $lowStockCount }}</em>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endcanany
                @endforeach
            </nav>

            <div class="erp-sidebar-foot">
                <div class="erp-user">
                    <span class="erp-avatar">{{ mb_strtoupper(mb_substr($user->username ?? 'U', 0, 2)) }}</span>
                    <span class="erp-user-text">
                        <strong>{{ $user->username ?? '' }}</strong>
                        <span>{{ $user?->getRoleNames()->implode('، ') ?: 'مستخدم' }}</span>
                        @if ($scopeLabel = \App\Support\Tenancy::describe())
                            <span style="color:#9FC3BF" title="{{ $scopeLabel }}">{{ $scopeLabel }}</span>
                        @endif
                    </span>
                </div>
            </div>
        </aside>
        <div class="erp-backdrop" id="erpBackdrop"></div>

        {{-- ================= المحتوى ================= --}}
        <div class="erp-main">
            <header class="erp-topbar">
                <button type="button" class="erp-icon-btn" id="erpSidebarToggle" aria-label="إظهار أو إخفاء القائمة" aria-controls="erpSidebar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="{{ $icons['menu'] }}"/></svg>
                </button>

                <div class="erp-page-title">
                    <h1>{{ $pageTitle }}</h1>
                    <p>{{ $pageGroup ? $pageGroup . '، ' : '' }}{{ now()->locale('ar')->translatedFormat('l j F Y') }}</p>
                </div>

                <div class="erp-top-actions">
                    @can('manage_sales')
                        @unless (request()->routeIs('pos.*'))
                            <a href="{{ $route('pos.index') }}" class="erp-top-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="{{ $icons['plus'] }}"/></svg>
                                <span>بيع جديد</span>
                            </a>
                        @endunless
                    @endcan

                    <a href="{{ $route('notifications.index') ?? '#' }}" class="erp-icon-btn" aria-label="الإشعارات{{ $unread ? ' (' . $unread . ' غير مقروءة)' : '' }}" title="الإشعارات">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons['bell'] }}"/></svg>
                        @if ($unread)
                            <span class="erp-badge">{{ $unread > 99 ? '99+' : $unread }}</span>
                        @endif
                    </a>

                    <div class="erp-menu" id="erpUserMenu">
                        <button type="button" class="erp-icon-btn" aria-haspopup="true" aria-expanded="false" aria-label="قائمة المستخدم" style="width:auto;padding:0 6px 0 10px;gap:8px;display:inline-flex;align-items:center">
                            <span class="erp-avatar" style="width:30px;height:30px;border-radius:8px;font-size:12px;background:var(--ink)">{{ mb_strtoupper(mb_substr($user->username ?? 'U', 0, 2)) }}</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="width:16px;height:16px"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="erp-menu-panel" hidden>
                            <div class="erp-menu-head">
                                <strong>{{ $user->username ?? '' }}</strong>
                                <span>{{ $user->email ?? '' }}</span>
                            </div>
                            <a href="{{ $route('profile.edit') ?? '#' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons['user'] }}"/></svg>
                                الملف الشخصي
                            </a>
                            <a href="{{ $route('notifications.index') ?? '#' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons['bell'] }}"/></svg>
                                الإشعارات{{ $unread ? ' (' . $unread . ')' : '' }}
                            </a>
                            <hr>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="is-danger">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:inherit"><path d="{{ $icons['logout'] }}"/></svg>
                                    تسجيل الخروج
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="erp-content" id="erpContent">
                @if (session('error'))
                    <div class="erp-flash is-error" role="alert">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="{{ $icons['alert'] }}"/></svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @hasSection('header')
                    <div class="mb-6">@yield('header')</div>
                @endif

                @isset($header)
                    <div class="mb-6">{{ $header }}</div>
                @endisset

                @isset($slot)
                    {{ $slot }}
                @endisset

                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script>
        (function () {
            const store = {
                get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
                set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
            };
            const sidebar = document.getElementById('erpSidebar');
            const backdrop = document.getElementById('erpBackdrop');
            const isMobile = () => window.matchMedia('(max-width: 1024px)').matches;

            // طي القائمة (يُحفظ لكل متصفح)
            if (store.get('erp.sidebar') === 'collapsed' && !isMobile()) sidebar.classList.add('is-collapsed');
            document.getElementById('erpSidebarToggle').addEventListener('click', () => {
                if (isMobile()) {
                    sidebar.classList.toggle('is-open');
                    backdrop.classList.toggle('is-open', sidebar.classList.contains('is-open'));
                } else {
                    sidebar.classList.toggle('is-collapsed');
                    store.set('erp.sidebar', sidebar.classList.contains('is-collapsed') ? 'collapsed' : 'open');
                }
            });
            backdrop.addEventListener('click', () => { sidebar.classList.remove('is-open'); backdrop.classList.remove('is-open'); });

            // المجموعات: تُفتح مجموعة الصفحة الحالية، والباقي حسب آخر اختيار
            document.querySelectorAll('.erp-group').forEach(g => {
                const key = 'erp.group.' + g.dataset.group;
                const saved = store.get(key);
                const closed = g.dataset.active === '1' ? false : saved === 'closed';
                g.classList.toggle('is-closed', closed);
                const btn = g.querySelector('.erp-group-toggle');
                btn.setAttribute('aria-expanded', String(!closed));
                btn.addEventListener('click', () => {
                    const nowClosed = !g.classList.contains('is-closed');
                    g.classList.toggle('is-closed', nowClosed);
                    btn.setAttribute('aria-expanded', String(!nowClosed));
                    store.set(key, nowClosed ? 'closed' : 'open');
                });
            });

            // إبقاء العنصر النشط ظاهراً داخل القائمة
            const active = sidebar.querySelector('.erp-link.is-active');
            if (active) active.scrollIntoView({ block: 'nearest' });

            // قائمة المستخدم
            const menu = document.getElementById('erpUserMenu');
            const trigger = menu.querySelector('button');
            const panel = menu.querySelector('.erp-menu-panel');
            const closeMenu = () => { panel.hidden = true; trigger.setAttribute('aria-expanded', 'false'); };
            trigger.addEventListener('click', e => {
                e.stopPropagation();
                panel.hidden = !panel.hidden;
                trigger.setAttribute('aria-expanded', String(!panel.hidden));
            });
            document.addEventListener('click', e => { if (!menu.contains(e.target)) closeMenu(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMenu(); });
        })();
    </script>
</body>

</html>
