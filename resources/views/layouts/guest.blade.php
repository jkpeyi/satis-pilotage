<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-800 antialiased">
<div class="flex min-h-full items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">
        <div class="mb-8 flex items-center justify-center gap-2">
            <span class="grid size-9 place-items-center rounded-lg bg-brand-700 text-sm font-bold text-white">SP</span>
            <span class="text-lg font-semibold text-slate-900">Satis Pilotage</span>
        </div>
        {{ $slot }}
    </div>
</div>
</body>
</html>
