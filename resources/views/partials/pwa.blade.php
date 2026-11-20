{{-- تطبيق سطح المكتب/الجوال (PWA): الأيقونة + ملف التعريف + عامل الخدمة --}}
<link rel="manifest" href="{{ asset('manifest.json') }}">
<meta name="theme-color" content="#0F5C5A">
<meta name="application-name" content="الصيدلية">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="الصيدلية">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/favicon-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<script>
    if ('serviceWorker' in navigator && (location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname))) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(@json(asset('sw.js'))).catch(() => {});
        });
        // تسجيل الخروج: حذف نسخة نقطة البيع المحفوظة (فيها بيانات العملاء). الفواتير غير المرفوعة تبقى.
        document.addEventListener('submit', e => {
            const f = e.target;
            if (f && f.action && f.action.split('?')[0] === @json(route('logout')) && navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({ type: 'logout' });
            }
        }, true);
    }
</script>
