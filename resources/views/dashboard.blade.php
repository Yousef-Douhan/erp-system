@extends('layouts.app') {{-- أو اسم الـ layout الأساسي لديك --}}

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('لوحة التحكم (Dashboard)') }}
    </h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- شبكة البطاقات الإحصائية -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <!-- إجمالي الفواتير -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-slate-400">إجمالي الفواتير</p>
                        <h4 class="text-2xl font-extrabold text-slate-800 mt-1">{{ $totalSalesCount ?? 0 }}</h4>
                    </div>
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                </div>

                <!-- إجمالي المبيعات (المبالغ) -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-slate-400">إجمالي المبيعات</p>
                        <h4 class="text-2xl font-extrabold text-slate-900 mt-1">{{ number_format($totalSalesAmount ?? 0, 2) }}</h4>
                    </div>
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>

                <!-- المبالغ المدفوعة -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-slate-400">المبالغ المدفوعة</p>
                        <h4 class="text-2xl font-extrabold text-emerald-600 mt-1">{{ number_format($totalPaidAmount ?? 0, 2) }}</h4>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>

                <!-- عدد العملاء -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-slate-400">إجمالي العملاء</p>
                        <h4 class="text-2xl font-extrabold text-slate-800 mt-1">{{ $customersCount ?? 0 }}</h4>
                    </div>
                    <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                </div>

            </div>

            <!-- جدول أحدث الفواتير -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-800">أحدث فواتير المبيعات</h3>
                    <a href="{{ route('sales.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">عرض الكل &larr;</a>
                </div>

                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50 text-slate-500 text-xs font-bold text-right">
                        <tr>
                            <th class="px-4 py-3">رقم الفاتورة</th>
                            <th class="px-4 py-3">العميل</th>
                            <th class="px-4 py-3">الإجمالي</th>
                            <th class="px-4 py-3">الحالة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                        @if(isset($latestSales) && count($latestSales) > 0)
                            @foreach($latestSales as $sale)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ $sale->invoice_number }}</td>
                                    <td class="px-4 py-3">{{ $sale->customer->name ?? $sale->customer_name ?? '---' }}</td>
                                    <td class="px-4 py-3 font-bold text-slate-900">{{ number_format($sale->total, 2) }}</td>
                                    <td class="px-4 py-3">
                                        @if($sale->status == 'paid')
                                            <span class="px-2 py-0.5 text-xs font-bold bg-emerald-50 text-emerald-600 rounded-md">مدفوعة</span>
                                        @elseif($sale->status == 'partial')
                                            <span class="px-2 py-0.5 text-xs font-bold bg-amber-50 text-amber-600 rounded-md">جزئي</span>
                                        @else
                                            <span class="px-2 py-0.5 text-xs font-bold bg-rose-50 text-rose-600 rounded-md">غير مدفوعة</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-slate-400 text-xs">لا توجد مبيعات حديثة مسجلة.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

        </div>
    </div>
@endsection