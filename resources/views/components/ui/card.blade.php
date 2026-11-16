@props(['title' => null])

<section {{ $attributes->class('rounded-lg border border-slate-200 bg-white shadow-sm') }}>
    @if ($title || isset($actions))
        <header class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-3">
            <h2 class="font-semibold text-slate-900">{{ $title }}</h2>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div class="p-5">{{ $slot }}</div>
</section>
