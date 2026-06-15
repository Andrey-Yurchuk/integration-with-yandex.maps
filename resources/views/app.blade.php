<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon_io/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon_io/favicon-16x16.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/favicon_io/apple-touch-icon.png">

        @vite(['resources/js/app.ts', 'resources/css/app.css'])
        @inertiaHead
    </head>
    <body class="bg-slate-50 font-sans text-slate-900 antialiased">
        @inertia
    </body>
</html>
