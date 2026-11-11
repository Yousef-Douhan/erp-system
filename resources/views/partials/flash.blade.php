{{-- رسائل النجاح والأخطاء (تُستخدم في الصفحات التي لا تعرضها) --}}
@if (($withSuccess ?? false) && session('success'))
    <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl text-sm font-bold">
        {{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-4 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl text-xs">
        <ul class="list-disc list-inside space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
