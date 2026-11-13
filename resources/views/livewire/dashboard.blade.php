<div>
    <x-ui.page-header title="Tableau de bord" description="Vue d'ensemble du patrimoine applicatif Satis." />

    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($counts as $label => $count)
            <div class="rounded-lg border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <dt class="text-sm text-slate-500">{{ $label }}</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ $count }}</dd>
            </div>
        @endforeach
    </dl>

    <p class="mt-6 text-sm text-slate-600">
        Effort estimé des problèmes non résolus : <strong class="tabular-nums">{{ rtrim(rtrim(number_format($openEffort, 1, ',', ' '), '0'), ',') }} j/h</strong>.
        <a href="{{ route('issues.index') }}" wire:navigate class="text-brand-700 hover:underline">Voir les problèmes</a>
    </p>
    <p class="mt-2 text-sm text-slate-500">Les tableaux de bord détaillés arrivent avec les rapports (S5).</p>
</div>
