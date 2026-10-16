<?php

return [

    /*
    | Off (the default), the app is a single shop on the default connection —
    | local development and the test suite. On, every request is routed by host:
    |
    |   repair.damatech.sy          → public site (landing page, shop finder)
    |   admin.repair.damatech.sy    → platform admin (tenant management)
    |   {slug}.repair.damatech.sy   → that shop, on its own database
    */
    'enabled' => (bool) env('TENANCY_ENABLED', false),

    'central_domain' => env('TENANCY_CENTRAL_DOMAIN', 'repair.damatech.sy'),

    'admin_subdomain' => env('TENANCY_ADMIN_SUBDOMAIN', 'admin'),

    // Connection holding the tenants and admins tables.
    'central_connection' => env('TENANCY_CENTRAL_CONNECTION', 'central'),

    // Each shop's database is this prefix + its slug (dashes become underscores).
    // The database user needs ALL on `<prefix>%`.* to create and drop them.
    'database_prefix' => env('TENANCY_DB_PREFIX', 'repair_t_'),

    // Subdomains a shop can never take.
    'reserved' => [
        'www', 'admin', 'api', 'app', 'mail', 'smtp', 'imap', 'pop', 'ftp', 'ns1', 'ns2',
        'cdn', 'static', 'assets', 'status', 'help', 'support', 'docs', 'blog', 'panel',
        'dashboard', 'login', 'billing', 'staging', 'dev',
    ],

    // Run as `<command> <host>` after a shop is created to issue its HTTPS
    // certificate and nginx site. Empty skips the step (SSL marked "skipped").
    'ssl_command' => env('TENANCY_SSL_COMMAND', ''),

    // Module switches a platform admin can turn on or off per shop. Each maps to
    // the shop's `feature_<key>` setting (App\Support\Features).
    'features' => [
        'sales' => 'المبيعات',
        'purchases' => 'المشتريات',
        'repairs' => 'الصيانة',
        'technicians' => 'خبراء الصيانة',
        'services' => 'الخدمات',
        'inventory' => 'الجرد والمخزون',
        'expenses' => 'المصاريف',
        'reports' => 'التقارير',
    ],

    // Self-service sign-up on the public site (/register). Each sign-up creates a
    // shop on a free trial that the admin panel can extend or convert.
    'signup' => [
        'enabled' => (bool) env('TENANCY_SIGNUP_ENABLED', true),
        'trial_days' => (int) env('TENANCY_SIGNUP_TRIAL_DAYS', 14),
        // Ceiling on sign-ups across all visitors per day — each one creates a
        // database and a Let's Encrypt certificate (50 per week per domain).
        'daily_limit' => (int) env('TENANCY_SIGNUP_DAILY_LIMIT', 20),
    ],

    // Cookies the single-shop install set for the whole central domain, expired
    // on every response so they stop shadowing the per-host ones. Remove once
    // they have aged out (sessions last SESSION_LIFETIME minutes).
    'legacy_cookies' => [
        'domain' => env('TENANCY_LEGACY_COOKIE_DOMAIN'),
        'names' => array_filter(explode(',', (string) env('TENANCY_LEGACY_COOKIE_NAMES', ''))),
    ],
];
