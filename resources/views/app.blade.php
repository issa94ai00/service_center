<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="phone-store">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <meta name="theme-color" content="#172554" />
        <link rel="manifest" href="/manifest.json" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" />
        <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png" />
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png" />
        <meta name="mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-title" content="الهواتف" />
        <meta name="description" content="نظام متكامل لمحلات الهواتف ومراكز الصيانة: المبيعات والمشتريات، استلام أجهزة الصيانة وتتبّعها، المخزون والصندوق والتقارير — من داماتك لتقنية المعلومات." />
        <title>{{ app(\App\Tenancy\Tenancy::class)->tenant()?->name ?? config('app.name') }}</title>
        <script>
            // Apply the saved theme before paint so there is no flash of the wrong mode.
            (function () {
                try {
                    var stored = localStorage.getItem('theme');
                    var dark = stored === 'dark' || (stored !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', dark);
                    var meta = document.querySelector('meta[name="theme-color"]');
                    if (meta) meta.content = dark ? '#020617' : '#172554';
                    var loc = localStorage.getItem('locale') || 'ar';
                    document.documentElement.lang = loc;
                    document.documentElement.dir = loc === 'ar' ? 'rtl' : 'ltr';
                } catch (e) {}
            })();
        </script>
        <script>window.__TENANCY__ = @json(app(\App\Tenancy\Tenancy::class)->frontendContext());</script>
        @vite(['resources/js/app.js'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>