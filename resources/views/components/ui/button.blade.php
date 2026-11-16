@props(['variant' => 'primary', 'href' => null, 'type' => 'button', 'size' => 'md'])

@php
    $classes = \Illuminate\Support\Arr::toCssClasses([
        'inline-flex items-center justify-center gap-1.5 rounded-md font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:opacity-50',
        'px-3 py-2 text-sm' => $size === 'md',
        'px-2.5 py-1.5 text-xs' => $size === 'sm',
        'bg-brand-700 text-white shadow-sm hover:bg-brand-600' => $variant === 'primary',
        'border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50' => $variant === 'secondary',
        'bg-red-600 text-white shadow-sm hover:bg-red-500' => $variant === 'danger',
        'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => $variant === 'ghost',
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
