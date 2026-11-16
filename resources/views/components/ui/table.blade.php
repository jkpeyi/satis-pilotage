{{-- Usage: <x-ui.table><x-slot:head><th>…</th></x-slot:head> <tr>…</tr> </x-ui.table> --}}
<div {{ $attributes->class('overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm') }}>
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 [&_th]:px-4 [&_th]:py-3">
            <tr>{{ $head }}</tr>
        </thead>
        <tbody class="divide-y divide-slate-100 [&_td]:px-4 [&_td]:py-3 [&_td]:align-top">
            {{ $slot }}
        </tbody>
    </table>
</div>
