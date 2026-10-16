<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="robots" content="noindex" />
        <title>{{ $tenant?->name ?? config('app.name') }}</title>
        <style>
            body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px;
                font-family: 'Segoe UI', Tahoma, system-ui, sans-serif; background: #070b17; color: #e2e8f0; text-align: center; }
            .card { max-width: 440px; width: 100%; border: 1px solid rgba(255,255,255,.1); border-radius: 24px; padding: 36px 28px; background: rgba(15,23,42,.8); }
            h1 { margin: 0 0 12px; font-size: 20px; color: #fff; }
            p { margin: 0; line-height: 1.8; color: #94a3b8; }
            a { display: inline-block; margin-top: 24px; padding: 12px 22px; border-radius: 14px; font-weight: 700; text-decoration: none;
                color: #020617; background: linear-gradient(to left, #22d3ee, #3b82f6); }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>{{ $tenant?->name ?? 'المتجر غير متاح' }}</h1>
            <p>{{ $message }}</p>
            <a href="{{ $centralUrl }}">الصفحة الرئيسية</a>
        </div>
    </body>
</html>
