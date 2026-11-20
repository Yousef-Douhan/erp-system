{{--
    منع إغلاق الوردية وعلى هذا الجهاز فواتير بيعت بدون إنترنت ولم تُرفع بعد
    (وإلا يُعدّ الصندوق وفيه نقد لم يُسجَّل على السيرفر فيظهر زيادة وهمية).
--}}
<script>
(function () {
    let pending = 0;
    try {
        const list = JSON.parse(localStorage.getItem('pos_offline_queue_v1') || '[]');
        pending = (Array.isArray(list) ? list : []).filter(e => String(e.user_id) === @json((string) $shiftUserId) && e.status !== 'synced').length;
    } catch (e) {}
    if (!pending) return;
    document.querySelectorAll('form.js-shift-close').forEach(form => {
        const box = document.createElement('div');
        box.className = 'x-flash is-error';
        box.setAttribute('role', 'alert');
        box.innerHTML = 'على هذا الجهاز <b class="num">' + pending + '</b> فاتورة بيعت بدون إنترنت ولم تُرفع بعد. ' +
            'افتح <a href="{{ route('pos.index') }}">نقطة البيع</a> والنت شغال حتى تُرفع، ثم أغلق الوردية.';
        form.prepend(box);
        form.querySelectorAll('button').forEach(b => { b.disabled = true; });
        form.addEventListener('submit', e => e.preventDefault(), true);
    });
})();
</script>
