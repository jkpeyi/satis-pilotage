<div>
    <x-ui.page-header title="Tableau de bord" description="Vue d'ensemble du patrimoine applicatif Satis." />

    <dl class="grid gap-4 sm:grid-cols-3">
        @foreach ($counts as $label => $count)
            <div class="rounded-lg border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <dt class="text-sm text-slate-500">{{ $label }}</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ $count }}</dd>
            </div>
        @endforeach
    </dl>

    <p class="mt-8 text-sm text-slate-500">Les indicateurs de dette (états, écarts, problèmes) arriveront avec le catalogue et les rapports.</p>
</div>
