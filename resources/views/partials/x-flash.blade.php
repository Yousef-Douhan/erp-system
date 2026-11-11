{{-- رسائل النجاح وأخطاء التحقق للصفحات الجديدة (رسائل session('error') يعرضها الإطار) --}}
@if (session('success'))
    <div class="x-flash" role="status">{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="x-flash is-error" role="alert">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
