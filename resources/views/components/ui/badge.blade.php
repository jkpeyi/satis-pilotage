@props(['color' => 'gray'])

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
    'bg-slate-100 text-slate-700' => $color === 'gray',
    'bg-emerald-50 text-emerald-700' => $color === 'green',
    'bg-amber-50 text-amber-800' => $color === 'amber',
    'bg-red-50 text-red-700' => $color === 'red',
    'bg-violet-50 text-violet-700' => $color === 'violet',
    'bg-brand-50 text-brand-700' => $color === 'blue',
]) }}>{{ $slot }}</span>
