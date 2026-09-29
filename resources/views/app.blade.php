<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="phone-store">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <meta name="theme-color" content="#172554" />
        <title>{{ config('app.name') }}</title>
        @vite(['resources/js/app.js'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>