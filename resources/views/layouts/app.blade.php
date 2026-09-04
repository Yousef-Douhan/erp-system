<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'ERP & Pharmacy System') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans bg-slate-100 text-slate-900 antialiased overflow-hidden" dir="rtl" x-data="{ sidebarOpen: true }">
    <div class="h-screen flex overflow-hidden">

        <!-- Sidebar (القائمة الجانبية على اليمين - ثابتة ولا تحرك الصفحة) -->
        <aside :class="sidebarOpen ? 'w-72' : 'w-20'"
            class="bg-white border-l border-slate-200 flex flex-col justify-between shrink-0 select-none shadow-sm transition-all duration-300 relative z-20 h-full">
            <div class="flex flex-col h-full overflow-hidden">

                <!-- Logo / Brand -->
                <div class="h-18 flex items-center px-5 border-b border-slate-100 gap-3.5 bg-slate-50/50 shrink-0">
                    <div
                        class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-black shadow-lg shadow-indigo-200 text-sm tracking-wider shrink-0">
                        ERP
                    </div>
                    <div x-show="sidebarOpen" class="transition-opacity duration-200 truncate">
                        <span class="block font-black text-slate-800 text-sm tracking-tight">نظام الأدوية و ERP</span>
                        <span class="block text-[10px] font-semibold text-slate-400">Pharmacy & Management</span>
                    </div>
                </div>

                <!-- Navigation Links (حركة الماوس والسكرول ستكون هنا فقط داخل القائمة) -->
                <nav class="flex-1 overflow-y-auto p-3 space-y-1.5 text-xs font-semibold custom-scrollbar">

                    <!-- Dashboard -->
                    <a href="{{ Route::has('dashboard') ? route('dashboard') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('dashboard') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="الرئيسية (Overview)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">الرئيسية (Overview)</span>
                    </a>

                    <!-- قسم النواة (System Core) -->
                    <div x-show="sidebarOpen" class="pt-4 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">
                        النواة والعمليات (Core)
                    </div>

                    <a href="{{ Route::has('organizations.index') ? route('organizations.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('organizations.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="المنظمات (Organizations)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">المنظمات (Organizations)</span>
                    </a>

                    <a href="{{ Route::has('branches.index') ? route('branches.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('branches.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="الفروع (Branches)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">الفروع (Branches)</span>
                    </a>

                    <a href="{{ Route::has('pos.index') ? route('pos.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('pos.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="نقطة البيع (Pharmacy POS)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">نقطة البيع (Pharmacy POS)</span>
                    </a>

                    <a href="{{ Route::has('customers.index') ? route('customers.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('customers.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="العملاء (Customers)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">العملاء (Customers)</span>
                    </a>

                    <a href="{{ Route::has('suppliers.index') ? route('suppliers.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('suppliers.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="الموردين (Suppliers)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">الموردين (Suppliers)</span>
                    </a>

                    <!-- قسم الوصفات الطبية (Prescriptions) -->
                    <div x-show="sidebarOpen" class="pt-4 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">
                        الروشتات الطبية
                    </div>

                    <a href="{{ Route::has('prescriptions.index') ? route('prescriptions.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('prescriptions.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="الوصفات الطبية (Prescriptions)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">الوصفات الطبية (Prescriptions)</span>
                    </a>

                    <!-- روابط إضافية -->
                    <a href="{{ route('drug-alerts.index') }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('drug-alerts.*') ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="التنبيهات الدوائية">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">التنبيهات الدوائية</span>
                    </a>

                    <a href="{{ route('inventory-transactions.index') }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('inventory-transactions.*') ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="الحركات المخزنية">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">الحركات المخزنية</span>
                    </a>

                    <a href="{{ route('audit-logs.index') }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition {{ request()->routeIs('audit-logs.*') ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="سجلات التدقيق">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">سجلات التدقيق</span>
                    </a>

                    <!-- قسم الحسابات والإدارة المالية -->
                    <div x-show="sidebarOpen" class="pt-4 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">
                        الحسابات والمالية (Accounting)
                    </div>

                    <a href="{{ Route::has('accounts.index') ? route('accounts.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('accounts.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="دليل الحسابات (Accounts)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">دليل الحسابات (Accounts)</span>
                    </a>

                    <a href="{{ Route::has('journal-entries.index') ? route('journal-entries.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('journal-entries.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="القيود اليومية (Journal Entries)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">القيود اليومية (Journal Entries)</span>
                    </a>

                    <a href="{{ Route::has('payment-methods.index') ? route('payment-methods.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('payment-methods.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="طرق الدفع (Payment Methods)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">طرق الدفع (Payment Methods)</span>
                    </a>

                    <a href="{{ Route::has('debts.index') ? route('debts.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('debts.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="الديون والذمم (Debts)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">الديون والذمم (Debts)</span>
                    </a>

                    <a href="{{ Route::has('customer-accounts.index') ? route('customer-accounts.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('customer-accounts.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="حسابات العملاء (Customer Accounts)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">حسابات العملاء (Customer Accounts)</span>
                    </a>

                    <a href="{{ Route::has('supplier-accounts.index') ? route('supplier-accounts.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('supplier-accounts.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="حسابات الموردين (Supplier Accounts)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">حسابات الموردين (Supplier Accounts)</span>
                    </a>

                    <a href="{{ Route::has('cash-registers.index') ? route('cash-registers.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('cash-registers.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="الصناديق/الخزن (Cash Registers)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 9V3a1 1 0 00-1-1H8a1 1 0 00-1 1v6m10 0a2 2 0 012 2v6a2 2 0 01-2 2H7a2 2 0 01-2-2v-6a2 2 0 012-2m10 0H7" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">الصناديق/الخزن (Cash Registers)</span>
                    </a>

                    <a href="{{ Route::has('expenses.index') ? route('expenses.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('expenses.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="المصروفات (Expenses)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">المصروفات (Expenses)</span>
                    </a>

                    <a href="{{ Route::has('sales.index') ? route('sales.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('sales.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="المبيعات (Sales)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">المبيعات (Sales)</span>
                    </a>

                    <a href="{{ Route::has('purchases.index') ? route('purchases.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('purchases.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="المشتريات (Purchases)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">المشتريات (Purchases)</span>
                    </a>

                    <!-- قسم المخزون والمنتجات -->
                    <div x-show="sidebarOpen" class="pt-4 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">
                        المخزون والمنتجات
                    </div>

                    <a href="{{ Route::has('categories.index') ? route('categories.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('categories.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="التصنيفات (Categories)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">التصنيفات (Categories)</span>
                    </a>

                    <a href="{{ Route::has('brands.index') ? route('brands.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('brands.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="الماركات (Brands)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">الماركات (Brands)</span>
                    </a>

                    <a href="{{ Route::has('manufacturers.index') ? route('manufacturers.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('manufacturers.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="المصنعين (Manufacturers)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">المصنعين (Manufacturers)</span>
                    </a>

                    <a href="{{ Route::has('units.index') ? route('units.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('units.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="الوحدات (Units)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">الوحدات (Units)</span>
                    </a>

                    <a href="{{ Route::has('products.index') ? route('products.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('products.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="المنتجات (Products)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">المنتجات (Products)</span>
                    </a>

                    <a href="{{ Route::has('batches.index') ? route('batches.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('batches.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="الدفعات (Batches)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">الدفعات (Batches)</span>
                    </a>

                    <a href="{{ Route::has('stock-settings.index') ? route('stock-settings.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('stock-settings.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="إعدادات المخزون (Min/Max)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">إعدادات المخزون (Min/Max)</span>
                    </a>

                    <!-- قسم الإدارة -->
                    <div x-show="sidebarOpen" class="pt-4 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">
                        إدارة النظام
                    </div>

                    <a href="{{ Route::has('users.index') ? route('users.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('users.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="المستخدمين (Users)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">المستخدمين (Users)</span>
                    </a>

                    <a href="{{ Route::has('employees.index') ? route('employees.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('employees.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="الموظفين (Employees)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">الموظفين (Employees)</span>
                    </a>

                    <a href="{{ Route::has('employee-branches.index') ? route('employee-branches.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('employee-branches.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="فروع الموظفين (Employee Branches)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">فروع الموظفين (Employee Branches)</span>
                    </a>

                    <a href="{{ Route::has('notifications.index') ? route('notifications.index') : '#' }}"
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->routeIs('notifications.*') ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600' }}"
                        title="الإشعارات (Notifications)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">الإشعارات (Notifications)</span>
                    </a>
                </nav>
            </div>

            <!-- زر تسجيل الخروج -->
            <div class="p-3 border-t border-slate-100 bg-slate-50/50 shrink-0">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl text-rose-600 bg-rose-50 hover:bg-rose-100 font-bold text-xs transition-all shadow-sm"
                        title="تسجيل الخروج">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span x-show="sidebarOpen" class="truncate">تسجيل الخروج</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content Wrapper (ثابت ولا يتحرك مع سكرول الصفحة الكاملة، ومحتواه الداخلي فقط هو القابل للتمرير) -->
        <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">

            <!-- Top Header -->
            <header
                class="h-20 bg-white border-b border-slate-200 px-6 sm:px-8 flex items-center justify-between shrink-0 shadow-sm">
                
                <div class="flex items-center gap-4">
                    <!-- زر تبديل القائمة الجانبية (3 شرطات) -->
                    <button @click="sidebarOpen = !sidebarOpen" 
                        class="p-2.5 rounded-xl bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 transition-colors focus:outline-none"
                        title="تبديل القائمة الجانبية">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <div class="flex items-center gap-3">
                        <div
                            class="w-11 h-11 rounded-2xl bg-slate-900 text-white font-black flex items-center justify-center text-sm shadow-md shrink-0">
                            YD
                        </div>
                        <div>
                            <div class="text-xs font-bold text-slate-800">يوسف حسن دوحان</div>
                            <div class="text-[11px] font-medium text-indigo-600">System Admin & Developer</div>
                        </div>
                    </div>
                </div>

                <div class="text-left">
                    <h1 class="text-sm md:text-base font-black text-slate-800">ERP & Pharmacy Dashboard</h1>
                    <p class="text-[11px] text-slate-400 font-medium">Wednesday, September 2, 2026, 10:22 PM EEST</p>
                </div>
            </header>

            <!-- Page Content (هنا فقط يتم عمل Scroll بالماوس داخل الصفحة إن كان المحتوى طويلاً دون تحريك كامل الموقع) -->
            <main class="flex-1 overflow-y-auto p-8">
                @isset($header)
                    <div class="mb-6">
                        {{ $header }}
                    </div>
                @endisset

                @yield('content')
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

</body>

</html>