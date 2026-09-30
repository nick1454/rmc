<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="alternate icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/favicon.svg">

        <title>{{ config('app.name', 'RMC - Ready Mix Concrete ERP') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
    </head>
    <body class="h-full font-sans antialiased text-slate-800 bg-slate-100">
        <div id="app" class="h-full"></div>
    </body>
</html>
