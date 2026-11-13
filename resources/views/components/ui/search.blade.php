@props(['placeholder' => 'Rechercher…'])

<input type="search" placeholder="{{ $placeholder }}"
    {{ $attributes->class('block w-full max-w-xs rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/30') }}>
