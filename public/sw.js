/*
 * عامل الخدمة (Service Worker) — يخلّي النظام يتثبّت كتطبيق ويفتح نقطة البيع بدون إنترنت.
 *
 * - صفحة نقطة البيع: من الشبكة أولاً، وتُحفظ آخر نسخة سليمة منها لتُفتح وقت الانقطاع.
 * - باقي الصفحات: من الشبكة فقط (بيانات حساسة ومتغيرة)، ووقت الانقطاع تظهر صفحة «لا يوجد اتصال».
 * - الملفات الثابتة (CSS/JS/الخطوط/الأيقونات): من الذاكرة مع تحديثها بالخلفية.
 * - لا يُخزَّن أي طلب POST، ولا أي رد فيه رسالة نجاح/خطأ.
 */
const VERSION = 'v1';
const STATIC_CACHE = 'erp-static-' + VERSION;
const PAGE_CACHE = 'erp-pos-page';            // ثابت عبر الإصدارات: لا نفقد نسخة نقطة البيع عند التحديث

const BASE = self.registration.scope;          // مثال: http://localhost/  أو  http://host/erp-system/public/
const POS_URL = new URL('pos', BASE).href;
const OFFLINE_URL = new URL('offline.html', BASE).href;
const PRECACHE = [
    OFFLINE_URL,
    new URL('js/pos-offline.js', BASE).href,
    new URL('css/erp-theme.css', BASE).href,
    new URL('icons/icon-192.png', BASE).href,
    new URL('manifest.json', BASE).href,
];
const CDN_HOSTS = ['fonts.googleapis.com', 'fonts.gstatic.com', 'cdn.jsdelivr.net'];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(c => Promise.all(PRECACHE.map(u => c.add(new Request(u, { cache: 'reload' })).catch(() => null))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys.filter(k => k.startsWith('erp-static-') && k !== STATIC_CACHE).map(k => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

// تسجيل الخروج: تُحذف نسخة نقطة البيع (فيها أسماء العملاء وديونهم)
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'logout') {
        event.waitUntil(caches.delete(PAGE_CACHE));
    }
});

const stripQuery = u => u.split('#')[0].split('?')[0];

async function savePosPage(response) {
    if (!response || !response.ok || response.redirected || response.type !== 'basic') return;
    const text = await response.clone().text();
    // صفحة فيها رسالة «تم حفظ الفاتورة» أو أخطاء لا تُحفظ (وإلا تظهر وقت الانقطاع وتمسح المسودة)
    if (text.includes('data-pos-flash="1"') || !text.includes('id="posForm"')) return;
    const cache = await caches.open(PAGE_CACHE);
    await cache.put(POS_URL, new Response(text, { headers: { 'Content-Type': 'text/html; charset=utf-8', 'X-From-Offline-Cache': '1' } }));
}

async function posPage(request) {
    try {
        const res = await fetch(request);
        savePosPage(res).catch(() => {});
        return res;
    } catch (e) {
        const cached = await caches.match(POS_URL, { cacheName: PAGE_CACHE });
        return cached || caches.match(OFFLINE_URL);
    }
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cached = await cache.match(request, { ignoreSearch: false });
    const network = fetch(request).then(res => {
        if (res && (res.ok || res.type === 'opaque')) cache.put(request, res.clone()).catch(() => {});
        return res;
    }).catch(() => null);
    if (cached) { network.catch(() => {}); return cached; }
    const res = await network;
    if (res) return res;
    // نسخة بدون رقم الإصدار (?v=...) إن وُجدت
    const loose = await cache.match(request, { ignoreSearch: true });
    return loose || Response.error();
}

self.addEventListener('fetch', event => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    const sameOrigin = url.origin === self.location.origin;
    const isPos = sameOrigin && stripQuery(req.url) === POS_URL;

    if (req.mode === 'navigate') {
        if (isPos) { event.respondWith(posPage(req)); return; }
        if (sameOrigin) {
            event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
        }
        return;
    }

    // تحديث النسخة المحفوظة من نقطة البيع بالخلفية (من الصفحة نفسها كل 10 دقائق)
    if (isPos && req.headers.get('X-POS-Cache') === '1') {
        event.respondWith(fetch(req).then(res => { savePosPage(res).catch(() => {}); return res; }));
        return;
    }

    const path = url.pathname;
    const isStatic = sameOrigin && /\.(css|js|woff2?|ttf|png|jpe?g|svg|ico|webp|json)$/i.test(path)
        && !path.includes('/api/') && !path.endsWith('/sw.js');
    if (isStatic || CDN_HOSTS.includes(url.hostname)) {
        event.respondWith(staleWhileRevalidate(req));
    }
});
