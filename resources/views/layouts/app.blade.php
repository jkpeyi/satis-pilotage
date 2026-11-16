<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-800 antialiased">
<div class="flex min-h-full">
    <aside class="hidden w-60 shrink-0 flex-col border-r border-slate-200 bg-white md:flex">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex h-14 items-center gap-2 border-b border-slate-200 px-5">
            <span class="grid size-7 place-items-center rounded-md bg-brand-700 text-xs font-bold text-white">SP</span>
            <span class="font-semibold text-slate-900">Satis Pilotage</span>
        </a>

        <nav class="flex-1 space-y-6 px-3 py-4 text-sm">
            <div class="space-y-1">
                <x-ui.nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Tableau de bord</x-ui.nav-link>
                <x-ui.nav-link :href="route('catalog.index')" :active="request()->routeIs('catalog.*')">Catalogue</x-ui.nav-link>
            </div>
            <div class="space-y-1">
                <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Qualification</p>
                <x-ui.nav-link :href="route('issues.index')" :active="request()->routeIs('issues.*')">Problèmes</x-ui.nav-link>
                <x-ui.nav-link :href="route('business-rules.index')" :active="request()->routeIs('business-rules.*')">Règles métier</x-ui.nav-link>
            </div>
            <div class="space-y-1">
                <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Référentiel</p>
                <x-ui.nav-link :href="route('products.index')" :active="request()->routeIs('products.*')">Produits</x-ui.nav-link>
                <x-ui.nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">Clients</x-ui.nav-link>
                <x-ui.nav-link :href="route('versions.index')" :active="request()->routeIs('versions.*')">Versions</x-ui.nav-link>
            </div>
            <div class="space-y-1">
                <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Paramètres</p>
                <x-ui.nav-link :href="route('technologies.index')" :active="request()->routeIs('technologies.*')">Technologies</x-ui.nav-link>
            </div>
        </nav>

        <div class="border-t border-slate-200 p-4 text-sm">
            <p class="truncate font-medium text-slate-900">{{ auth()->user()?->name }}</p>
            <p class="truncate text-xs text-slate-500">{{ auth()->user()?->email }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="text-xs font-medium text-slate-500 hover:text-slate-900">Se déconnecter</button>
            </form>
        </div>
    </aside>

    <main class="min-w-0 flex-1">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-8">
            <x-ui.flash />
            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
