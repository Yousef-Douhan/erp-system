/*
 * البيع بدون إنترنت — نقطة البيع
 *
 * - الفواتير التي تُباع وقت انقطاع النت تُحفظ على هذا الجهاز (localStorage) برقم مؤقت OFF-...
 *   ولكل فاتورة رمز فريد (uuid) فلا تُحفظ على السيرفر مرتين مهما أُعيد رفعها.
 * - لما يرجع النت تُرفع تلقائياً بالترتيب وبوقتها الأصلي، ويرجع لكل واحدة رقمها الرسمي.
 * - المخزون وديون العملاء المعروضة = بيانات الصفحة − الفواتير التي لم تصل السيرفر بعد.
 *
 * لا يحتاج مكتبات. كل قراءة/كتابة للتخزين داخل try/catch.
 */
(function () {
    'use strict';

    const QUEUE_KEY = 'pos_offline_queue_v1';
    const SEQ_KEY = 'pos_offline_seq_v1';
    const KEEP_SYNCED_MS = 3 * 24 * 3600 * 1000;   // الفواتير المرفوعة تبقى 3 أيام للعرض ثم تُحذف من الجهاز
    const BATCH = 20;

    const cfg = {
        userId: null, branchId: null, pingUrl: '', syncUrl: '', pageUrl: '', renderedAt: null,
        storeName: '', userName: '', onChange: null,
    };
    let net = navigator.onLine ? 'unknown' : 'offline';   // online | offline | auth | unknown
    let syncing = null;
    let lastError = '';

    // ---------------- التخزين ----------------
    function read() {
        try {
            const v = JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
            return Array.isArray(v) ? v : [];
        } catch (e) { return []; }
    }
    function write(list) {
        try { localStorage.setItem(QUEUE_KEY, JSON.stringify(list)); return true; }
        catch (e) { return false; }
    }
    function update(uuid, patch) {
        const list = read();
        const i = list.findIndex(e => e.uuid === uuid);
        if (i >= 0) { list[i] = Object.assign({}, list[i], patch); write(list); }
    }
    function purge() {
        const now = Date.now();
        const list = read();
        const kept = list.filter(e => e.status !== 'synced' || !e.synced_local || now - e.synced_local < KEEP_SYNCED_MS);
        if (kept.length !== list.length) write(kept);
    }

    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        const b = new Uint8Array(16);
        (window.crypto || {}).getRandomValues ? crypto.getRandomValues(b) : b.forEach((_, i) => { b[i] = Math.random() * 256 | 0; });
        b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
        const h = [...b].map(x => x.toString(16).padStart(2, '0')).join('');
        return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
    }

    function nextNumber() {
        const d = new Date();
        const day = String(d.getFullYear()).slice(2) + String(d.getMonth() + 1).padStart(2, '0') + String(d.getDate()).padStart(2, '0');
        let seq = { day: '', n: 0 };
        try { seq = JSON.parse(localStorage.getItem(SEQ_KEY) || 'null') || seq; } catch (e) {}
        seq = seq.day === day ? { day, n: seq.n + 1 } : { day, n: 1 };
        try { localStorage.setItem(SEQ_KEY, JSON.stringify(seq)); } catch (e) {}
        return `OFF-${cfg.branchId || 0}-${day}-${String(seq.n).padStart(3, '0')}`;
    }

    // ---------------- الشبكة ----------------
    function csrf() {
        const m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }
    function setCsrf(token) {
        if (!token) return;
        const m = document.querySelector('meta[name="csrf-token"]');
        if (m) m.setAttribute('content', token);
        document.querySelectorAll('input[name="_token"]').forEach(i => { i.value = token; });
    }

    async function timedFetch(url, opts, ms) {
        const ctl = window.AbortController ? new AbortController() : null;
        const t = ctl ? setTimeout(() => ctl.abort(), ms) : null;
        try {
            return await fetch(url, Object.assign({ credentials: 'same-origin', cache: 'no-store', signal: ctl ? ctl.signal : undefined }, opts));
        } finally { if (t) clearTimeout(t); }
    }

    function setNet(v) {
        if (net !== v) { net = v; changed(); }
    }

    /** يفحص الاتصال بالسيرفر فعلاً (الواي فاي قد يكون متصلاً بدون إنترنت). */
    async function reachable(ms = 3500) {
        if (!navigator.onLine) { setNet('offline'); return 'offline'; }
        try {
            const r = await timedFetch(cfg.pingUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }, ms);
            if (r.status === 401 || r.status === 419 || r.redirected) { setNet('auth'); return 'auth'; }
            if (!r.ok) { setNet('offline'); return 'offline'; }
            const j = await r.json().catch(() => null);
            if (!j || !j.ok) { setNet('auth'); return 'auth'; }
            setCsrf(j.token);
            setNet('online');
            return 'online';
        } catch (e) {
            setNet('offline');
            return 'offline';
        }
    }

    // ---------------- الطابور ----------------
    function enqueue(entry) {
        const e = Object.assign({
            uuid: uuid(),
            number: nextNumber(),
            user_id: cfg.userId,
            user_name: cfg.userName,
            branch_id: cfg.branchId,
            at: new Date().toISOString(),
            status: 'pending',
            message: '',
            attempts: 0,
        }, entry);
        e.payload = Object.assign({}, e.payload, { uuid: e.uuid, number: e.number, user_id: e.user_id, at: e.at });
        const list = read();
        list.push(e);
        if (!write(list)) return null;
        // اطلب من المتصفح ألا يحذف بيانات الموقع عند امتلاء التخزين
        try { navigator.storage && navigator.storage.persist && navigator.storage.persist(); } catch (x) {}
        changed();
        return e;
    }

    const mine = e => String(e.user_id) === String(cfg.userId);
    const notOnServer = e => e.status !== 'synced';

    function counts() {
        const list = read();
        return {
            pending: list.filter(e => mine(e) && (e.status === 'pending')).length,
            failed: list.filter(e => mine(e) && e.status === 'failed').length,
            others: list.filter(e => !mine(e) && notOnServer(e)).length,
            total: list.length,
        };
    }

    /**
     * ما يجب طرحه من بيانات الصفحة: كل فاتورة لم تصل السيرفر، أو وصلته بعد توليد الصفحة
     * (الصفحة المحفوظة قديمة فلا تحتوي عليها).
     */
    function deductions(renderedAtIso) {
        const rendered = renderedAtIso ? Date.parse(renderedAtIso) : 0;
        const stock = {}, debt = {}, rx = new Set();
        read().forEach(e => {
            const counted = notOnServer(e) || (e.synced_at && Date.parse(e.synced_at) > rendered);
            if (!counted || String(e.branch_id) !== String(cfg.branchId)) return;
            (e.lines || []).forEach(l => { stock[l.pid] = (stock[l.pid] || 0) + (Number(l.base_qty) || 0); });
            if (e.customer_id && e.debt_delta) debt[e.customer_id] = (debt[e.customer_id] || 0) + Number(e.debt_delta);
            if (e.payload && e.payload.prescription_id) rx.add(String(e.payload.prescription_id));
        });
        return { stock, debt, rx };
    }

    async function postBatch(batch, retried) {
        const r = await timedFetch(cfg.syncUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ sales: batch.map(e => e.payload) }),
        }, 30000);
        if (r.status === 419 && !retried) {
            // رمز الحماية انتهى أثناء الانقطاع: نجيب رمزاً جديداً ونعيد المحاولة مرة واحدة
            if (await reachable() === 'online') return postBatch(batch, true);
        }
        if (r.status === 401 || r.status === 419 || r.redirected) { setNet('auth'); throw new Error('auth'); }
        if (r.status === 403) throw new Error('forbidden');
        if (!r.ok) throw new Error('http ' + r.status);
        return r.json();
    }

    /** يرفع فواتير المستخدم الحالي المنتظرة. يرجع عدد ما رُفع. */
    function sync() {
        if (syncing) return syncing;
        syncing = (async () => {
            let done = 0;
            try {
                if (!counts().pending) return 0;
                if (await reachable() !== 'online') return 0;
                for (let guard = 0; guard < 50; guard++) {
                    const batch = read().filter(e => mine(e) && e.status === 'pending').slice(0, BATCH);
                    if (!batch.length) break;
                    batch.forEach(e => update(e.uuid, { attempts: (e.attempts || 0) + 1 }));
                    const res = await postBatch(batch, false);
                    const results = (res && res.results) || [];
                    let progressed = false;
                    results.forEach(x => {
                        if (!x || !x.uuid) return;
                        if (x.status === 'ok' || x.status === 'duplicate') {
                            update(x.uuid, {
                                status: 'synced', invoice_number: x.invoice_number, sale_id: x.sale_id,
                                needs_review: !!x.needs_review, review_note: x.review_note || '', message: '',
                                synced_at: res.server_time, synced_local: Date.now(),
                            });
                            done++; progressed = true;
                        } else if (x.status === 'rejected') {
                            update(x.uuid, { status: 'failed', message: x.message || 'رفض السيرفر الفاتورة.' });
                            progressed = true;
                        } else {
                            update(x.uuid, { message: x.message || '' });
                        }
                    });
                    if (!progressed) break;   // خطأ مؤقت: نعيد المحاولة في الدورة القادمة
                }
                lastError = '';
            } catch (e) {
                lastError = e && e.message === 'auth' ? 'انتهت الجلسة — سجّل الدخول من جديد لرفع الفواتير.'
                    : e && e.message === 'forbidden' ? 'حسابك لا يملك صلاحية رفع الفواتير.'
                    : 'تعذّر الرفع الآن، ستُعاد المحاولة تلقائياً.';
            } finally {
                syncing = null;
                purge();
                changed();
            }
            return done;
        })();
        return syncing;
    }

    function retry(id) { update(id, { status: 'pending', message: '' }); changed(); return sync(); }
    function discard(id) { write(read().filter(e => e.uuid !== id)); changed(); }

    /** تحديث النسخة المحفوظة من صفحة نقطة البيع (عامل الخدمة يخزنها) لتكون البيانات حديثة وقت الانقطاع. */
    async function refreshPageCache() {
        if (net !== 'online' || !cfg.pageUrl) return;
        try { await timedFetch(cfg.pageUrl, { headers: { 'X-POS-Cache': '1', Accept: 'text/html' }, cache: 'no-cache' }, 20000); } catch (e) {}
    }

    // ---------------- الواجهة ----------------
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = n => (Math.round((Number(n) || 0) * 100) / 100).toFixed(2);
    const fmtTime = iso => { try { const d = new Date(iso); return d.toLocaleDateString('en-GB') + ' ' + d.toTimeString().slice(0, 5); } catch (e) { return iso; } };

    function changed() {
        renderBadge();
        renderPanel();
        if (typeof cfg.onChange === 'function') { try { cfg.onChange(state()); } catch (e) {} }
    }
    function state() { return Object.assign({ net, syncing: !!syncing, error: lastError }, counts()); }

    function renderBadge() {
        const b = document.getElementById('posNet');
        if (!b) return;
        const c = counts();
        let tone = 'ok', text = 'متصل';
        if (net === 'offline') { tone = 'off'; text = 'بدون إنترنت'; }
        else if (net === 'auth') { tone = 'off'; text = 'سجّل الدخول'; }
        else if (net === 'unknown') { tone = 'wait'; text = 'جارٍ الفحص'; }
        if (c.failed) tone = 'bad';
        const queued = c.pending + c.failed;
        b.dataset.tone = tone;
        b.innerHTML = `<span class="pos-net-dot" aria-hidden="true"></span><span>${text}</span>` +
            (queued ? `<span class="pos-net-count num" title="فواتير على الجهاز لم تُرفع">${queued}</span>` : '');
        b.title = queued ? `${queued} فاتورة محفوظة على الجهاز بانتظار الرفع` : 'حالة الاتصال والفواتير المحفوظة على الجهاز';
    }

    function ensurePanel() {
        let p = document.getElementById('posQueue');
        if (p) return p;
        p = document.createElement('div');
        p.id = 'posQueue';
        p.className = 'pos-queue';
        p.setAttribute('role', 'dialog');
        p.setAttribute('aria-modal', 'true');
        p.setAttribute('aria-labelledby', 'posQueueTitle');
        p.innerHTML = `<div class="pos-queue-card"><div class="pos-queue-head"><h3 id="posQueueTitle">الفواتير المحفوظة على الجهاز</h3>
            <button type="button" class="pos-queue-close" data-q="close" aria-label="إغلاق">إغلاق</button></div><div id="posQueueBody"></div></div>`;
        document.body.appendChild(p);
        p.addEventListener('click', e => {
            if (e.target === p) return closePanel();
            const btn = e.target.closest('[data-q]');
            if (!btn) return;
            const act = btn.dataset.q, id = btn.dataset.id;
            if (act === 'close') closePanel();
            if (act === 'sync') sync();
            if (act === 'retry') retry(id);
            if (act === 'print') { const en = read().find(x => x.uuid === id); if (en) printReceipt(en); }
            if (act === 'discard') {
                if (btn.dataset.armed) discard(id);
                else { btn.dataset.armed = '1'; btn.textContent = 'تأكيد الحذف نهائياً'; btn.classList.add('is-armed'); }
            }
        });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && p.classList.contains('open')) { e.stopPropagation(); closePanel(); } }, true);
        return p;
    }
    function openPanel() { ensurePanel().classList.add('open'); renderPanel(); }
    function closePanel() { const p = document.getElementById('posQueue'); if (p) p.classList.remove('open'); }

    const STATUS = {
        pending: ['wait', 'بانتظار الرفع'],
        failed: ['bad', 'رفضها السيرفر'],
        synced: ['ok', 'رُفعت'],
    };

    function renderPanel() {
        const p = document.getElementById('posQueue');
        if (!p || !p.classList.contains('open')) return;
        const c = counts();
        const list = read().filter(mine).slice().reverse().slice(0, 60);
        const netText = net === 'online' ? 'متصل بالسيرفر' : net === 'auth' ? 'متصل لكن الجلسة انتهت — سجّل الدخول من جديد' : net === 'offline' ? 'لا يوجد اتصال بالسيرفر' : 'جارٍ فحص الاتصال';
        let html = `<div class="pos-queue-status" data-tone="${net === 'online' ? 'ok' : 'off'}">
            <span>${esc(netText)}</span>
            <button type="button" class="pos-queue-btn" data-q="sync" ${c.pending && !syncing ? '' : 'disabled'}>${syncing ? 'جارٍ الرفع…' : 'ارفع الآن'}</button></div>`;
        if (lastError) html += `<div class="pos-queue-msg is-bad">${esc(lastError)}</div>`;
        if (c.others) html += `<div class="pos-queue-msg">يوجد ${c.others} فاتورة لمستخدم آخر على هذا الجهاز، تُرفع عندما يسجّل دخوله ويفتح نقطة البيع.</div>`;
        if (c.failed) html += `<div class="pos-queue-msg is-bad">فواتير رفضها السيرفر: البيع حصل فعلاً، فراجع السبب مع المدير ثم «أعد المحاولة». لا تحذفها إلا إذا سجّلتها يدوياً.</div>`;
        if (!list.length) {
            html += '<div class="pos-queue-empty">لا توجد فواتير محفوظة على هذا الجهاز.</div>';
        } else {
            html += '<div class="pos-queue-list">' + list.map(e => {
                const [tone, label] = STATUS[e.status] || STATUS.pending;
                const actions = [`<button type="button" class="pos-queue-btn is-ghost" data-q="print" data-id="${e.uuid}">طباعة</button>`];
                if (e.status === 'failed') {
                    actions.push(`<button type="button" class="pos-queue-btn" data-q="retry" data-id="${e.uuid}">أعد المحاولة</button>`);
                    actions.push(`<button type="button" class="pos-queue-btn is-danger" data-q="discard" data-id="${e.uuid}">حذف من الجهاز</button>`);
                }
                return `<div class="pos-queue-item">
                    <div><strong class="num">${esc(e.number)}</strong>${e.invoice_number ? ` ← <span class="num">${esc(e.invoice_number)}</span>` : ''}
                        <div class="pos-queue-sub"><span class="num">${esc(fmtTime(e.at))}</span> · ${esc(e.customer_name || 'زبون نقدي')} · <span class="num">${money(e.total)}</span> ₪${e.method_name ? ' · ' + esc(e.method_name) : ''}</div>
                        ${e.message ? `<div class="pos-queue-sub is-bad">${esc(e.message)}</div>` : ''}
                        ${e.needs_review ? '<div class="pos-queue-sub is-warn">حُفظت وعليها ملاحظة للمدير</div>' : ''}
                    </div>
                    <span class="pos-queue-tag" data-tone="${tone}">${label}</span>
                    <div class="pos-queue-actions">${actions.join('')}</div>
                </div>`;
            }).join('') + '</div>';
        }
        document.getElementById('posQueueBody').innerHTML = html;
    }

    // ---------------- إيصال مؤقت ----------------
    function printReceipt(e) {
        const rows = (e.lines || []).map(l => `<tr><td>${esc(l.name)}<br><small>${esc(l.qty)} × ${money(l.price)} ${esc(l.unit || '')}</small></td><td class="n">${money(l.qty * l.price)}</td></tr>`).join('');
        const html = `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>${esc(e.number)}</title>
<style>
  @page { size: 80mm auto; margin: 4mm; }
  body { font-family: 'IBM Plex Sans Arabic', Tahoma, sans-serif; font-size: 12px; color: #000; margin: 0; width: 72mm; }
  h1 { font-size: 15px; margin: 0 0 2px; text-align: center; }
  .c { text-align: center; } .n { text-align: left; white-space: nowrap; }
  table { width: 100%; border-collapse: collapse; margin: 6px 0; }
  td { padding: 3px 0; border-bottom: 1px dashed #999; vertical-align: top; }
  .tot td { border: 0; font-weight: 700; font-size: 13px; }
  .note { border: 1px solid #000; padding: 4px; margin-top: 6px; text-align: center; font-size: 11px; }
</style></head><body>
<h1>${esc(cfg.storeName || 'الصيدلية')}</h1>
<div class="c">فاتورة مؤقتة <b>${esc(e.number)}</b></div>
<div class="c">${esc(fmtTime(e.at))} · ${esc(e.user_name || '')}</div>
${e.customer_name ? `<div class="c">العميل: ${esc(e.customer_name)}</div>` : ''}
<table>${rows}
<tr class="tot"><td>الإجمالي</td><td class="n">${money(e.total)} ₪</td></tr>
${Number(e.discount) > 0 ? `<tr class="tot"><td>الخصم</td><td class="n">${money(e.discount)}</td></tr>` : ''}
<tr class="tot"><td>المدفوع${e.method_name ? ' (' + esc(e.method_name) + ')' : ''}</td><td class="n">${money(e.paid)}</td></tr>
${Number(e.due) > 0 ? `<tr class="tot"><td>دين على العميل</td><td class="n">${money(e.due)}</td></tr>` : ''}
${Number(e.change) > 0 ? `<tr class="tot"><td>الباقي للزبون</td><td class="n">${money(e.change)}</td></tr>` : ''}
</table>
<div class="note">بيعت بدون إنترنت. الرقم الرسمي للفاتورة يصدر عند رجوع الاتصال${e.invoice_number ? ': ' + esc(e.invoice_number) : ''}.</div>
</body></html>`;
        const f = document.createElement('iframe');
        f.style.cssText = 'position:fixed;width:0;height:0;border:0;left:-9999px';
        document.body.appendChild(f);
        const d = f.contentWindow.document;
        d.open(); d.write(html); d.close();
        setTimeout(() => { try { f.contentWindow.focus(); f.contentWindow.print(); } catch (x) {} setTimeout(() => f.remove(), 1500); }, 200);
    }

    // ---------------- التشغيل ----------------
    function init(options) {
        Object.assign(cfg, options || {});
        purge();
        const b = document.getElementById('posNet');
        if (b) b.addEventListener('click', openPanel);
        renderBadge();

        window.addEventListener('online', () => { setNet('unknown'); sync().then(() => reachable()); });
        window.addEventListener('offline', () => setNet('offline'));

        // فحص أولي ثم رفع ما ينتظر، ثم تحديث النسخة المحفوظة من الصفحة
        setTimeout(async () => {
            if (await reachable() === 'online') { await sync(); setTimeout(refreshPageCache, 1500); }
        }, 600);
        // كل 20 ثانية: إن وُجد ما ينتظر نرفعه، وإلا نفحص الاتصال كل دقيقة
        let tick = 0;
        setInterval(() => {
            tick++;
            if (counts().pending) sync();
            else if (tick % 3 === 0) reachable();
        }, 20000);
        setInterval(refreshPageCache, 10 * 60 * 1000);
        return api;
    }

    const api = { init, reachable, enqueue, sync, retry, discard, deductions, counts, printReceipt, openPanel, state, read };
    window.PosOffline = api;

    // صفحة الورديات تستخدم هذا لمنع الإغلاق والفواتير على الجهاز لم تُرفع
    window.PosOfflinePending = function (userId) {
        return read().filter(e => String(e.user_id) === String(userId) && e.status !== 'synced').length;
    };
})();
