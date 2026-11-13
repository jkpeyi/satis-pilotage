@props(['href', 'active' => false])

<a href="{{ $href }}" wire:navigate
   {{ $attributes->class([
       'flex items-center rounded-md px-3 py-2 font-medium',
       'bg-brand-50 text-brand-700' => $active,
       'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $active,
   ]) }}>
    {{ $slot }}
</a>
