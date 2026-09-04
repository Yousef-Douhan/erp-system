
@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6" dir="rtl">
    
    <!-- رأس الصفحة العصري -->
    <div class="bg-gradient-to-r from-indigo-600 to-blue-500 rounded-2xl p-6 text-white shadow-sm mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-4">
            <div class="bg-white/10 p-3 rounded-xl backdrop-blur-sm">
                <i class="fas fa-bell fa-2x"></i>
            </div>
            <div>
                <h2 class="text-lg font-black mb-1">مركز التنبيهات والتحذيرات</h2>
                <p class="text-xs text-indigo-100 mb-0">متابعة حالة المخزون، النواقص، والأدوية التي وصلت للحد الأدنى</p>
            </div>
        </div>
        
        @if(auth()->user()->unreadNotifications()->count() > 0)
            <form action="{{ route('notifications.readAll') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="bg-white text-indigo-600 hover:bg-indigo-50 font-bold px-4 py-2.5 rounded-xl text-xs shadow-md transition flex items-center gap-2">
                    <i class="fas fa-check-double"></i> تحديد الكل كمقروء
                </button>
            </form>
        @else
            <div class="bg-white/10 px-4 py-2 rounded-xl backdrop-blur-sm text-xs font-bold">
                جميع الإشعارات مقروءة
            </div>
        @endif
    </div>

    <!-- جدول عرض الإشعارات -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="text-sm font-black text-slate-800">قائمة الإشعارات والتنبيهات</h3>
            <span class="text-xs text-slate-500 font-semibold">تحديث مباشر</span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                        <th class="p-3.5 font-bold">نوع التنبيه</th>
                        <th class="p-3.5 font-bold">التفاصيل والرسالة</th>
                        <th class="p-3.5 font-bold">الوقت</th>
                        <th class="p-3.5 font-bold text-center">الإجراء / الحالة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse(auth()->user()->notifications()->latest()->get() as $notification)
                        <tr class="{{ $notification->is_read ? 'bg-white text-slate-500' : 'bg-indigo-50/40 font-bold text-slate-800' }} transition-all">
                            
                            <!-- نوع التنبيه -->
                            <td class="p-3.5">
                                <div class="flex items-center gap-2">
                                    <div class="p-2 rounded-lg {{ $notification->is_read ? 'bg-slate-100 text-slate-400' : 'bg-amber-100 text-amber-600' }}">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-800 block">{{ $notification->title }}</span>
                                        @if(!$notification->is_read)
                                            <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-600 text-[10px] font-bold mt-0.5 inline-block">جديد</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- التفاصيل والرسالة -->
                            <td class="p-3.5 text-slate-600">
                                {{ $notification->message }}
                            </td>

                            <!-- الوقت -->
                            <td class="p-3.5 text-slate-400 font-medium">
                                <i class="far fa-clock ml-1"></i> {{ $notification->created_at->diffForHumans() }}
                            </td>

                            <!-- الإجراء / الحالة -->
                            <td class="p-3.5 text-center">
                                @if(!$notification->is_read)
                                    <button class="mark-as-read bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg font-bold shadow-sm transition text-[11px]" data-id="{{ $notification->id }}">
                                        <i class="fas fa-check ml-1"></i> قراءة
                                    </button>
                                @else
                                    <span class="px-3 py-1 rounded-lg bg-emerald-50 text-emerald-600 font-semibold text-[11px] inline-flex items-center gap-1">
                                        <i class="fas fa-check-circle"></i> مقروء
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-10 text-center text-slate-400">
                                <div class="mb-2 text-indigo-300 opacity-60">
                                    <i class="fas fa-bell-slash fa-3x"></i>
                                </div>
                                <h5 class="text-slate-700 font-bold text-sm mb-1">لا توجد إشعارات حالياً</h5>
                                <p class="text-xs text-slate-400">ممتاز! جميع مستويات المخزون والأدوية في الوضع الآمن.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Scripts & Icons -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<script>
$(document).ready(function() {
    $('.mark-as-read').click(function() {
        let notificationId = $(this).data('id');
        let btn = $(this);

        $.ajax({
            url: '/notifications/' + notificationId + '/read',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if(response.success) {
                    let row = btn.closest('tr');
                    row.removeClass('bg-indigo-50/40 font-bold text-slate-800').addClass('bg-white text-slate-500');
                    btn.replaceWith('<span class="px-3 py-1 rounded-lg bg-emerald-50 text-emerald-600 font-semibold text-[11px] inline-flex items-center gap-1"><i class="fas fa-check-circle"></i> مقروء</span>');
                }
            }
        });
    });
});
</script>
@endsection