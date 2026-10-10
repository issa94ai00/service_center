// Makes the app installable. Nothing is cached: prices, stock and balances must always
// come from the server, so a lost connection shows a notice instead of stale pages.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

const OFFLINE_PAGE = `<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>لا يوجد اتصال</title>
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
font-family:system-ui,sans-serif;background:#f8fafc;color:#0f172a;text-align:center;padding:16px}
button{margin-top:16px;padding:10px 20px;border:0;border-radius:10px;background:#2563eb;color:#fff;font-size:15px}</style>
</head><body><div><h1 style="font-size:20px">لا يوجد اتصال بالإنترنت</h1>
<p style="color:#64748b">تحقّق من الاتصال ثم أعد المحاولة.</p>
<button onclick="location.reload()">إعادة المحاولة</button></div></body></html>`;

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') return;
    event.respondWith(
        fetch(event.request).catch(() => new Response(OFFLINE_PAGE, { headers: { 'Content-Type': 'text/html; charset=utf-8' } }))
    );
});
