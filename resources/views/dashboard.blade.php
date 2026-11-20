@extends('layouts.app')

@section('title', 'الرئيسية')

@section('content')
@php
    $ic = [
        'pos'      => 'M4 4h16v10H4zM8 18h8M12 14v4M7 8h.01M11 8h.01M15 8h2',
        'receipt'  => 'M6 2h12v20l-3-2-3 2-3-2-3 2V2zm3 6h6m-6 4h6m-6 4h4',
        'users'    => 'M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9.5 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zM21 19v-1a4 4 0 0 0-3-3.87M15.5 3.13a3.5 3.5 0 0 1 0 6.74',
        'pill'     => 'M10.5 20.5 3.5 13.5a5 5 0 0 1 7-7l7 7a5 5 0 0 1-7 7zM8.5 8.5l7 7',
        'layers'   => 'm12 2 10 5-10 5L2 7l10-5zM2 12l10 5 10-5M2 17l10 5 10-5',
        'cart'     => 'M3 3h2l2.4 12.2a2 2 0 0 0 2 1.8h8.8a2 2 0 0 0 2-1.6L22 7H6M9 21h.01M18 21h.01',
        'truck'    => 'M1 4h13v12H1zM14 9h4l3 3v4h-7M5.5 19.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM17.5 19.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z',
        'wallet'   => 'M20 7H5a2 2 0 0 1 0-4h13v4M3 5v14a2 2 0 0 0 2 2h15V7M16 14h.01',
        'safe'     => 'M3 4h18v16H3zM7 20v2m10-2v2M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6zm0 0V7m3 5h2',
        'book'     => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14zM4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5',
        'chart'    => 'M3 3v18h18M8 17V9m5 8V5m5 12v-6',
        'shield'   => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4',
        'building' => 'M4 21V3h11v18M15 9h5v12M8 7h3m-3 4h3m-3 4h3M2 21h20',
        'alert'    => 'M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4m0 4h.01',
        'debt'     => 'M12 3v18M17 7H9.5a3 3 0 0 0 0 6h5a3 3 0 0 1 0 6H6',
        'box-in'   => 'M21 8v12H3V8M1 3h22v5H1zM12 11v6m-3-3 3 3 3-3',
        'cash'     => 'M2 6h20v12H2zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM6 12h.01M18 12h.01',
        'calendar' => 'M3 5h18v16H3zM16 3v4M8 3v4M3 10h18',
        'clock'    => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 6v6l4 2',
        'check'    => 'M20 6 9 17l-5-5',
        'arrow'    => 'M19 12H5m6-6-6 6 6 6',
        'undo'     => 'M9 14 4 9l5-5M4 9h11a5 5 0 0 1 0 10h-3',
        'clipboard'=> 'M9 4h6v3H9zM8 5H6v16h12V5h-2M9 12l2 2 4-4',
        'trend'    => 'm3 17 6-6 4 4 8-8M15 7h6v6',
    ];
    $openShift = Route::has('shifts.index') ? \App\Services\CashRegisterService::openShiftFor($user) : null;
    $svg = fn ($k, $w = 1.8) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $w . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $ic[$k] . '"/></svg>';
    $n = fn ($v) => number_format((float) $v, 2);
    $plural = fn ($c, $one, $many) => $c . ' ' . ($c == 1 ? $one : $many);

    // بطاقات الأقسام: تظهر فقط إذا كان الراوت موجوداً والمستخدم يملك إحدى صلاحياتها
    $tiles = [
        ['title' => 'نقطة البيع', 'desc' => 'بيع سريع بالبحث أو الباركود، بالحبة أو الشريط أو العلبة', 'route' => 'pos.index', 'can' => ['manage_sales'], 'icon' => 'pos', 'theme' => 'teal',
            'badge' => $stats['today_sales'] ? $plural($stats['today_sales'], 'فاتورة اليوم', 'فواتير اليوم') : 'لا مبيعات اليوم بعد', 'tone' => $stats['today_sales'] ? 'good' : 'muted'],
        ['title' => 'فواتير المبيعات', 'desc' => 'عرض الفواتير وتسجيل الدفعات والمرتجعات', 'route' => 'sales.index', 'can' => ['manage_sales'], 'icon' => 'receipt', 'theme' => 'blue',
            'badge' => $stats['unpaid_sales'] ? $plural($stats['unpaid_sales'], 'فاتورة غير مسددة', 'فواتير غير مسددة') : 'كل الفواتير مسددة', 'tone' => $stats['unpaid_sales'] ? 'warn' : 'good'],
        ['title' => 'العملاء والديون', 'desc' => 'بيانات العملاء والأرصدة ودفتر الديون', 'route' => 'customers.index', 'can' => ['manage_sales', 'view_customers'], 'icon' => 'users', 'theme' => 'violet',
            'badge' => $stats['over_limit'] ? $plural($stats['over_limit'], 'تجاوز حد الدين', 'تجاوزوا حد الدين') : ($stats['debtors'] ? $plural($stats['debtors'], 'عميل عليه دين', 'عملاء عليهم ديون') : 'لا ديون مستحقة'),
            'tone' => $stats['over_limit'] ? 'bad' : ($stats['debtors'] ? 'warn' : 'good')],
        ['title' => 'المرتجعات', 'desc' => 'إرجاع أصناف من فاتورة ورد المبلغ أو تخفيضه من الدين', 'route' => 'returns.index', 'can' => ['return_sales'], 'icon' => 'undo', 'theme' => 'rose',
            'badge' => 'من صفحة الفاتورة', 'tone' => 'muted'],
        ['title' => 'الورديات', 'desc' => 'فتح الوردية وإغلاقها بعدّ الصندوق ومعرفة الزيادة والعجز', 'route' => 'shifts.index', 'can' => ['manage_shifts', 'manage_accounting'], 'icon' => 'clock', 'theme' => 'teal',
            'badge' => $openShift ? 'ورديتك مفتوحة' : 'لا توجد وردية مفتوحة لك', 'tone' => $openShift ? 'good' : 'warn'],
        ['title' => 'المنتجات', 'desc' => 'الأصناف والأسعار والوحدات والباركود', 'route' => 'products.index', 'can' => ['view_pharmacy'], 'icon' => 'pill', 'theme' => 'green',
            'badge' => $stats['low_stock'] ? $plural($stats['low_stock'], 'صنف قارب على النفاد', 'أصناف قاربت على النفاد') : $plural($stats['products'], 'صنف نشط', 'صنف نشط'), 'tone' => $stats['low_stock'] ? 'warn' : 'muted'],
        ['title' => 'المخزون والصلاحية', 'desc' => 'الدفعات وتواريخ الانتهاء والحركات المخزنية', 'route' => 'batches.index', 'can' => ['view_pharmacy'], 'icon' => 'layers', 'theme' => 'amber',
            'badge' => $stats['expired'] ? $plural($stats['expired'], 'دفعة منتهية', 'دفعات منتهية') : ($stats['expiring'] ? $plural($stats['expiring'], 'دفعة تنتهي قريباً', 'دفعات تنتهي قريباً') : 'لا دفعات قريبة الانتهاء'),
            'tone' => $stats['expired'] ? 'bad' : ($stats['expiring'] ? 'warn' : 'good')],
        ['title' => 'الجرد', 'desc' => 'عدّ الرفوف ومطابقة الكميات مع النظام وتسوية الفروقات', 'route' => 'stock-counts.index', 'can' => ['stock_count'], 'icon' => 'clipboard', 'theme' => 'cyan',
            'badge' => 'جرد دوري', 'tone' => 'muted'],
        ['title' => 'المشتريات', 'desc' => 'فواتير الشراء من الموردين ومدفوعاتها', 'route' => 'purchases.index', 'can' => ['view_purchases'], 'icon' => 'cart', 'theme' => 'orange',
            'badge' => $stats['today_purchases'] ? $plural($stats['today_purchases'], 'فاتورة اليوم', 'فواتير اليوم') : 'لا مشتريات اليوم', 'tone' => 'muted'],
        ['title' => 'الموردون', 'desc' => 'بيانات الموردين وحد الائتمان وحساباتهم', 'route' => 'suppliers.index', 'can' => ['view_purchases'], 'icon' => 'truck', 'theme' => 'sky',
            'badge' => $stats['unpaid_purchases'] ? $plural($stats['unpaid_purchases'], 'فاتورة لم تُسدَّد', 'فواتير لم تُسدَّد') : $plural($stats['suppliers'], 'مورد', 'مورد'), 'tone' => $stats['unpaid_purchases'] ? 'warn' : 'muted'],
        ['title' => 'المصروفات', 'desc' => 'تسجيل مصاريف التشغيل اليومية ومتابعتها', 'route' => 'expenses.index', 'can' => ['manage_accounting'], 'icon' => 'wallet', 'theme' => 'rose',
            'badge' => $stats['today_expenses'] ? $plural($stats['today_expenses'], 'مصروف اليوم', 'مصاريف اليوم') : 'لا مصاريف اليوم', 'tone' => 'muted'],
        ['title' => 'الخزائن', 'desc' => 'أرصدة الخزائن وسندات القبض والصرف', 'route' => 'cash-registers.index', 'can' => ['manage_accounting'], 'icon' => 'safe', 'theme' => 'emerald',
            'badge' => $n($stats['cash']) . ' ₪ رصيد', 'tone' => 'muted'],
        ['title' => 'المحاسبة', 'desc' => 'دليل الحسابات والقيود وقائمة الدخل والميزانية', 'route' => 'accounts.index', 'can' => ['manage_accounting'], 'icon' => 'book', 'theme' => 'slate',
            'badge' => 'الحسابات والتقارير', 'tone' => 'muted'],
        ['title' => 'التقارير', 'desc' => 'الأرباح، مبيعات الموظفين، الأصناف الراكدة، مع تصدير Excel', 'route' => 'reports.profit', 'can' => ['view_reports'], 'icon' => 'trend', 'theme' => 'green',
            'badge' => 'هذا الشهر', 'tone' => 'muted'],
        ['title' => 'التحليلات', 'desc' => 'رسم المبيعات، الأكثر مبيعاً، الديون والمخزون', 'route' => 'analytics', 'can' => ['view_dashboard'], 'icon' => 'chart', 'theme' => 'indigo',
            'badge' => 'آخر 14 يوماً', 'tone' => 'muted'],
        ['title' => 'المستخدمون والصلاحيات', 'desc' => 'حسابات الموظفين والأدوار وما يستطيع كل منهم فعله', 'route' => 'users.index', 'can' => ['manage_users'], 'icon' => 'shield', 'theme' => 'slate',
            'badge' => $plural($stats['users'], 'مستخدم نشط', 'مستخدمين نشطين'), 'tone' => 'muted'],
        ['title' => 'الصيدلية والفروع', 'desc' => 'بيانات الصيدلية والفروع والموظفون', 'route' => 'branches.index', 'can' => ['manage_settings'], 'icon' => 'building', 'theme' => 'stone',
            'badge' => 'الإعدادات العامة', 'tone' => 'muted'],
    ];
    $tiles = array_values(array_filter($tiles, fn ($t) => Route::has($t['route']) && $user->canany($t['can'])));

    // اختصارات سريعة داخل الشريط الترحيبي
    $actions = array_values(array_filter([
        ['label' => 'بيع جديد', 'icon' => 'pos', 'route' => 'pos.index', 'can' => ['manage_sales']],
        ['label' => 'توريد بضاعة', 'icon' => 'box-in', 'route' => 'purchases.index', 'can' => ['create_purchases']],
        ['label' => 'فاتورة شراء', 'icon' => 'cart', 'route' => 'purchases.index', 'can' => ['view_purchases']],
        ['label' => 'تسجيل مصروف', 'icon' => 'wallet', 'route' => 'expenses.index', 'can' => ['manage_accounting']],
        ['label' => 'دفتر الديون', 'icon' => 'debt', 'route' => 'debts.index', 'can' => ['manage_sales', 'view_customers']],
    ], fn ($a) => Route::has($a['route']) && $user->canany($a['can'])));
@endphp

<style>
    .home { display: grid; grid-template-columns: minmax(0, 1fr); gap: 18px; max-width: 1480px; margin: 0 auto; }
    .home .num { font-variant-numeric: tabular-nums; direction: ltr; unicode-bidi: isolate; }
    .home svg { display: block; }

    /* ---------- الشريط الترحيبي ---------- */
    .home-hero {
        position: relative; overflow: hidden; border-radius: 20px; padding: 22px;
        display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 20px; align-items: stretch;
        background:
            radial-gradient(600px 260px at 85% -20%, rgba(127, 209, 200, .28), transparent 70%),
            radial-gradient(500px 300px at 5% 120%, rgba(56, 120, 220, .30), transparent 70%),
            linear-gradient(135deg, #0F5C5A 0%, #0D4547 45%, #12304A 100%);
        color: #fff; box-shadow: 0 10px 30px -12px rgba(16, 42, 43, .45);
    }
    .home-hero::after {
        content: ""; position: absolute; inset: 0; pointer-events: none; opacity: .5;
        background-image: radial-gradient(rgba(255,255,255,.09) 1px, transparent 1px); background-size: 18px 18px;
        mask-image: linear-gradient(to left, #000, transparent 60%); -webkit-mask-image: linear-gradient(to left, #000, transparent 60%);
    }
    .home-hero > * { position: relative; z-index: 1; min-width: 0; }
    .home-hello { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 18px; }
    .home-hello h2 { margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -.2px; }
    .home-hello p { margin: 4px 0 0; font-size: 13.5px; color: #B9D3D0; }
    .home-chip { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px; border-radius: 999px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); font-size: 13px; color: #E6F2F0; white-space: nowrap; }
    .home-chip svg { width: 15px; height: 15px; }

    .home-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .home-kpi {
        display: flex; align-items: center; gap: 12px; padding: 14px; border-radius: 14px; text-decoration: none; color: #fff;
        background: rgba(255,255,255,.09); border: 1px solid rgba(255,255,255,.14); backdrop-filter: blur(4px);
        transition: background .15s, transform .15s;
    }
    a.home-kpi:hover { background: rgba(255,255,255,.15); transform: translateY(-1px); }
    .home-kpi-icon { width: 44px; height: 44px; flex: none; border-radius: 12px; display: grid; place-items: center; color: #fff; box-shadow: inset 0 1px 0 rgba(255,255,255,.35), 0 6px 14px -6px rgba(0,0,0,.45); }
    .home-kpi-icon svg { width: 22px; height: 22px; }
    .home-kpi-label { font-size: 13px; color: #C7DCDA; }
    .home-kpi-value { font-size: 21px; font-weight: 700; line-height: 1.25; white-space: nowrap; }
    .home-kpi-value small { font-size: 12px; font-weight: 500; color: #B9D3D0; margin-inline-start: 3px; }
    .home-kpi-note { font-size: 12px; color: #A9C6C3; margin-top: 1px; }
    .home-noFigures { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 12px; background: rgba(255,255,255,.08); border: 1px dashed rgba(255,255,255,.22); font-size: 13.5px; color: #D3E5E3; }
    .home-noFigures svg { width: 20px; height: 20px; flex: none; }

    .home-hero-main { display: flex; flex-direction: column; gap: 16px; }
    .home-hero-main .home-hello { margin-bottom: 0; }
    .home-actions { margin-top: auto; display: flex; flex-wrap: wrap; gap: 8px; }
    .home-action {
        height: 40px; padding: 0 14px; display: inline-flex; align-items: center; gap: 8px; border-radius: 11px;
        font-size: 13.5px; font-weight: 600; color: #fff; text-decoration: none;
        background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18); transition: background .15s;
    }
    .home-action:hover { background: rgba(255,255,255,.18); }
    .home-action.is-main { background: #fff; color: var(--teal-strong); border-color: #fff; }
    .home-action.is-main:hover { background: #E9F5F3; }
    .home-action svg { width: 18px; height: 18px; }
    .home-action kbd { font: inherit; font-size: 11px; padding: 1px 6px; border-radius: 6px; background: var(--teal-soft); color: var(--teal); }

    /* ---------- يحتاج انتباهك ---------- */
    .home-attn { background: #fff; color: var(--ink); border-radius: 16px; padding: 14px; display: flex; flex-direction: column; gap: 8px; box-shadow: 0 8px 24px -14px rgba(0,0,0,.5); }
    .home-attn-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 2px 4px 4px; }
    .home-attn-head strong { font-size: 15px; display: inline-flex; align-items: center; gap: 8px; }
    .home-attn-head .count { min-width: 22px; height: 22px; padding: 0 6px; border-radius: 999px; display: inline-grid; place-items: center; font-size: 12px; font-weight: 700; background: var(--red-soft); color: var(--red); }
    .home-attn-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
    .home-attn-item { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 11px; text-decoration: none; color: inherit; border: 1px solid var(--line-soft); background: #FBFCFC; transition: border-color .15s, background .15s; }
    .home-attn-item:hover { border-color: var(--teal-line); background: var(--teal-soft); }
    .home-attn-dot { width: 32px; height: 32px; flex: none; border-radius: 9px; display: grid; place-items: center; }
    .home-attn-dot svg { width: 17px; height: 17px; }
    .home-attn-dot.red { background: var(--red-soft); color: var(--red); }
    .home-attn-dot.amber { background: var(--amber-soft); color: var(--amber); }
    .home-attn-dot.blue { background: #E7EEFB; color: #2F5BB7; }
    .home-attn-text { flex: 1; min-width: 0; font-size: 13.5px; line-height: 1.4; }
    .home-attn-text b { font-size: 15px; }
    .home-attn-go { width: 26px; height: 26px; flex: none; border-radius: 8px; display: grid; place-items: center; color: var(--muted); }
    .home-attn-go svg { width: 16px; height: 16px; }
    .home-attn-ok { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; text-align: center; padding: 14px; color: var(--muted); font-size: 13.5px; }
    .home-attn-ok span { width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; background: var(--green-soft); color: var(--green); }
    .home-attn-ok span svg { width: 22px; height: 22px; }
    .home-attn-ok strong { color: var(--ink); font-size: 14.5px; }

    /* ---------- بطاقات الأقسام ---------- */
    .home-section-head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; padding: 0 2px; }
    .home-section-head h3 { margin: 0; font-size: 17px; font-weight: 700; color: var(--ink); }
    .home-section-head span { font-size: 13px; color: var(--muted); }
    .home-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(232px, 1fr)); gap: 14px; }
    .home-tile {
        --c1: #0F5C5A; --c2: #3AA39A; --tint: #E5F1EF;
        position: relative; overflow: hidden; display: flex; flex-direction: column; gap: 10px; min-height: 168px; padding: 16px;
        border-radius: 16px; text-decoration: none; color: var(--ink); background: #fff; border: 1px solid var(--line);
        box-shadow: var(--shadow); transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .home-tile::before {
        content: ""; position: absolute; inset: 0; pointer-events: none;
        background: radial-gradient(220px 140px at 0% 0%, var(--tint), transparent 70%);
    }
    .home-tile > * { position: relative; }
    .home-tile:hover { transform: translateY(-3px); border-color: color-mix(in srgb, var(--c1) 35%, var(--line)); box-shadow: 0 14px 28px -16px color-mix(in srgb, var(--c1) 60%, transparent); }
    .home-tile:focus-visible { outline: 3px solid color-mix(in srgb, var(--c1) 40%, transparent); outline-offset: 2px; }
    .home-tile-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
    .home-tile-icon {
        width: 54px; height: 54px; flex: none; border-radius: 15px; display: grid; place-items: center; color: #fff;
        background: linear-gradient(140deg, var(--c2), var(--c1));
        box-shadow: inset 0 1px 0 rgba(255,255,255,.4), inset 0 -8px 14px rgba(0,0,0,.12), 0 8px 16px -8px var(--c1);
    }
    .home-tile-icon svg { width: 27px; height: 27px; }
    .home-tile-go { width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; color: var(--muted); background: var(--canvas); transition: background .15s, color .15s; }
    .home-tile-go svg { width: 17px; height: 17px; }
    .home-tile:hover .home-tile-go { background: var(--c1); color: #fff; }
    .home-tile h4 { margin: 0; font-size: 16px; font-weight: 700; }
    .home-tile p { margin: 2px 0 0; font-size: 13px; line-height: 1.55; color: var(--muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .home-badge { margin-top: auto; align-self: flex-start; display: inline-flex; align-items: center; gap: 6px; max-width: 100%; padding: 4px 10px; border-radius: 8px; font-size: 12.5px; font-weight: 600; background: var(--canvas); color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .home-badge::before { content: ""; width: 7px; height: 7px; flex: none; border-radius: 50%; background: currentColor; opacity: .7; }
    .home-badge.good { background: var(--green-soft); color: var(--green); }
    .home-badge.warn { background: var(--amber-soft); color: var(--amber); }
    .home-badge.bad { background: var(--red-soft); color: var(--red); }

    .home-tile.t-teal    { --c1: #0F5C5A; --c2: #34A79C; --tint: #E3F3F0; }
    .home-tile.t-blue    { --c1: #2754B8; --c2: #5B8DEF; --tint: #E9F0FD; }
    .home-tile.t-violet  { --c1: #5B3CC4; --c2: #8E73F0; --tint: #EFEBFD; }
    .home-tile.t-green   { --c1: #1F7A4D; --c2: #4CC384; --tint: #E6F6EC; }
    .home-tile.t-amber   { --c1: #B4690E; --c2: #F2B13C; --tint: #FDF3E1; }
    .home-tile.t-cyan    { --c1: #0E7490; --c2: #38BDD8; --tint: #E3F6FA; }
    .home-tile.t-orange  { --c1: #C2410C; --c2: #FB8A4C; --tint: #FEEEE5; }
    .home-tile.t-sky     { --c1: #1D6FA5; --c2: #4FAEE8; --tint: #E6F3FC; }
    .home-tile.t-rose    { --c1: #BE2B4E; --c2: #F06A8A; --tint: #FDEBEF; }
    .home-tile.t-emerald { --c1: #047857; --c2: #2FC08E; --tint: #E2F6EF; }
    .home-tile.t-slate   { --c1: #334155; --c2: #6B7C93; --tint: #EEF1F5; }
    .home-tile.t-indigo  { --c1: #3730A3; --c2: #6D68E8; --tint: #ECECFD; }
    .home-tile.t-stone   { --c1: #57534E; --c2: #8E877F; --tint: #F2F0EE; }

    /* ---------- شريط الحالة ---------- */
    .home-status { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 18px; padding: 10px 16px; border-radius: 14px; background: #fff; border: 1px solid var(--line); font-size: 13px; color: var(--muted); }
    .home-status span { display: inline-flex; align-items: center; gap: 6px; }
    .home-status svg { width: 15px; height: 15px; }
    .home-status b { color: var(--ink); font-weight: 600; }
    .home-status .online { color: var(--green); }
    .home-status .online i { width: 8px; height: 8px; border-radius: 50%; background: currentColor; box-shadow: 0 0 0 3px var(--green-soft); }
    .home-status .push { margin-inline-start: auto; }

    @media (max-width: 1280px) { .home-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 1024px) { .home-hero { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 640px) {
        .home-hero { padding: 16px; border-radius: 16px; }
        .home-hello h2 { font-size: 20px; }
        .home-kpis { gap: 8px; }
        .home-kpi { flex-direction: column; align-items: flex-start; gap: 8px; padding: 12px; }
        .home-kpi-icon { width: 36px; height: 36px; border-radius: 10px; }
        .home-kpi-icon svg { width: 18px; height: 18px; }
        .home-kpi-value { font-size: 17px; }
        .home-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .home-tile { min-height: 0; padding: 12px; gap: 8px; }
        .home-tile-icon { width: 42px; height: 42px; border-radius: 12px; }
        .home-tile-icon svg { width: 21px; height: 21px; }
        .home-tile-go { display: none; }
        .home-tile h4 { font-size: 14.5px; }
        .home-tile p { font-size: 12px; }
        .home-badge { font-size: 11.5px; padding: 3px 8px; }
        .home-status .push { margin-inline-start: 0; }
    }
    @media (prefers-reduced-motion: reduce) { .home-tile, .home-kpi { transition: none; } .home-tile:hover { transform: none; } }
</style>

<div class="home">
    {{-- ================= الشريط الترحيبي ================= --}}
    <section class="home-hero" aria-label="ملخص اليوم">
        <div class="home-hero-main">
            <div class="home-hello">
                <div>
                    <h2>{{ $greeting }}، {{ $user->username }}</h2>
                    <p>{{ $todayFigures ? 'هذه أرقام اليوم حتى الآن. اختر قسماً من الأسفل للمتابعة.' : 'اختر قسماً من الأسفل للبدء.' }}</p>
                </div>
                <span class="home-chip">{!! $svg('calendar') !!}{{ $todayLabel }}</span>
            </div>

            @if ($todayFigures)
                <div class="home-kpis">
                    <a class="home-kpi" href="{{ Route::has('sales.index') ? route('sales.index', ['filter' => 'today']) : '#' }}">
                        <span class="home-kpi-icon" style="background:linear-gradient(140deg,#5B8DEF,#2754B8)">{!! $svg('chart') !!}</span>
                        <span>
                            <span class="home-kpi-label">مبيعات اليوم</span>
                            <span class="home-kpi-value num" style="display:block">{{ $n($todayFigures['sales']) }}<small>₪</small></span>
                            <span class="home-kpi-note">{{ $plural($todayFigures['sales_count'], 'فاتورة', 'فواتير') }}</span>
                        </span>
                    </a>
                    <div class="home-kpi">
                        <span class="home-kpi-icon" style="background:linear-gradient(140deg,#4CC384,#1F7A4D)">{!! $svg('cash') !!}</span>
                        <span>
                            <span class="home-kpi-label">المحصَّل</span>
                            <span class="home-kpi-value num" style="display:block">{{ $n($todayFigures['collected']) }}<small>₪</small></span>
                            <span class="home-kpi-note">@if ($todayFigures['debt_collected'] > 0) منها تحصيل ديون: <span class="num">{{ $n($todayFigures['debt_collected']) }}</span> · @endif آجل اليوم: <span class="num">{{ $n($todayFigures['credit']) }}</span></span>
                        </span>
                    </div>
                    <a class="home-kpi" href="{{ Route::has('purchases.index') && $user->can('view_purchases') ? route('purchases.index') : '#' }}">
                        <span class="home-kpi-icon" style="background:linear-gradient(140deg,#FB8A4C,#C2410C)">{!! $svg('cart') !!}</span>
                        <span>
                            <span class="home-kpi-label">مشتريات اليوم</span>
                            <span class="home-kpi-value num" style="display:block">{{ $n($todayFigures['purchases']) }}<small>₪</small></span>
                            <span class="home-kpi-note">{{ $plural($todayFigures['purchases_count'], 'فاتورة', 'فواتير') }}</span>
                        </span>
                    </a>
                    <a class="home-kpi" href="{{ Route::has('expenses.index') && $user->can('manage_accounting') ? route('expenses.index') : '#' }}">
                        <span class="home-kpi-icon" style="background:linear-gradient(140deg,#F06A8A,#BE2B4E)">{!! $svg('wallet') !!}</span>
                        <span>
                            <span class="home-kpi-label">مصاريف اليوم</span>
                            <span class="home-kpi-value num" style="display:block">{{ $n($todayFigures['expenses']) }}<small>₪</small></span>
                            <span class="home-kpi-note">{{ $plural($todayFigures['expenses_count'], 'مصروف', 'مصاريف') }}</span>
                        </span>
                    </a>
                </div>
            @else
                <div class="home-noFigures">{!! $svg('shield') !!}<span>أرقام المبيعات والمصاريف تظهر لمن يملك صلاحية «عرض الأرقام والتحليلات».</span></div>
            @endif

            @if ($actions)
                <div class="home-actions">
                    @foreach ($actions as $i => $a)
                        <a href="{{ route($a['route']) }}" class="home-action {{ $i === 0 ? 'is-main' : '' }}">{!! $svg($a['icon'], 2) !!}{{ $a['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ================= يحتاج انتباهك ================= --}}
        <aside class="home-attn" aria-label="يحتاج انتباهك">
            <div class="home-attn-head">
                <strong>يحتاج انتباهك @if ($attention->isNotEmpty())<span class="count num">{{ $attention->count() }}</span>@endif</strong>
            </div>
            @if ($attention->isEmpty())
                <div class="home-attn-ok">
                    <span>{!! $svg('check', 2.2) !!}</span>
                    <strong>كل شيء على ما يرام</strong>
                    لا توجد تنبيهات تحتاج متابعة الآن.
                </div>
            @else
                <ul class="home-attn-list">
                    @foreach ($attention as $item)
                        <li>
                            <a class="home-attn-item" href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}">
                                <span class="home-attn-dot {{ $item['tone'] }}">{!! $svg($item['icon']) !!}</span>
                                <span class="home-attn-text"><b class="num">{{ $item['count'] }}</b> {{ $item['text'] }}</span>
                                <span class="home-attn-go">{!! $svg('arrow') !!}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </aside>
    </section>

    {{-- ================= الأقسام ================= --}}
    <div class="home-section-head">
        <h3>الأقسام</h3>
        <span>تظهر الأقسام المسموحة لدورك فقط</span>
    </div>

    <nav class="home-tiles" aria-label="أقسام النظام">
        @foreach ($tiles as $t)
            <a href="{{ route($t['route']) }}" class="home-tile t-{{ $t['theme'] }}">
                <div class="home-tile-top">
                    <span class="home-tile-icon">{!! $svg($t['icon'], 1.9) !!}</span>
                    <span class="home-tile-go">{!! $svg('arrow', 2) !!}</span>
                </div>
                <div>
                    <h4>{{ $t['title'] }}</h4>
                    <p>{{ $t['desc'] }}</p>
                </div>
                <span class="home-badge {{ $t['tone'] }}">{{ $t['badge'] }}</span>
            </a>
        @endforeach
    </nav>

    {{-- ================= شريط الحالة ================= --}}
    <footer class="home-status">
        <span class="online"><i></i>متصل</span>
        <span>{!! $svg('shield') !!}<b>{{ $user->username }}</b>@if ($roleName)<span style="color:var(--line)">|</span>{{ $roleName }}@endif</span>
        @if ($orgName)<span>{!! $svg('building') !!}{{ $orgName }}</span>@endif
        <span class="push">{!! $svg('calendar') !!}<span class="num">{{ now()->format('Y/m/d') }}</span></span>
        <span>{!! $svg('clock') !!}<span class="num" id="homeClock">{{ now()->format('H:i') }}</span></span>
    </footer>
</div>

<script>
(function () {
    const el = document.getElementById('homeClock');
    if (!el) return;
    const tick = () => { const d = new Date(); el.textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0') + ':' + String(d.getSeconds()).padStart(2, '0'); };
    tick(); setInterval(tick, 1000);
})();
</script>
@endsection
