<div>
    <x-ui.page-header :title="$form->version ? 'Modifier la version' : 'Nouvelle version'">
        <x-slot:breadcrumbs>
            <a href="{{ route('versions.index') }}" wire:navigate class="hover:text-slate-900">Versions</a>
            @if ($form->version)
                / <a href="{{ route('versions.show', $form->version) }}" wire:navigate class="hover:text-slate-900">{{ $form->version->name }}</a>
            @endif
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    <x-ui.card class="max-w-2xl">
        @if ($products->isEmpty())
            <x-ui.empty>
                Créez d'abord un produit.
                <a href="{{ route('products.create') }}" wire:navigate class="font-medium text-brand-700 underline">Nouveau produit</a>
            </x-ui.empty>
        @else
            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select label="Produit" wire:model="form.product_id" :options="$products" placeholder="Choisir…" required />
                    <x-ui.select label="Client" wire:model="form.customer_id" :options="$customers" placeholder="Aucun (version socle)"
                        hint="Sans client, la version est un socle : elle peut servir de référence neutre." />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Nom" wire:model="form.name" required hint="Ex. Satis Macro – Banque X" />
                    <x-ui.select label="Cycle de vie" wire:model="form.status" :options="$statuses" required
                        hint="Une perspective future (ex. Satis_SaaS) n'est jamais comptée comme application en production." />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select label="Responsable technique" wire:model="form.technical_owner_id" :options="$users" placeholder="À confirmer" />
                    <x-ui.select label="Suppléant" wire:model="form.deputy_id" :options="$users" placeholder="À confirmer" hint="Remplace le responsable technique en son absence." />
                </div>
                <x-ui.select label="Statut de vérification" wire:model="form.verification_status" :options="$verificationStatuses" required
                    hint="Vérification des informations de cette fiche (dépôts, environnements, responsables)." />
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="form.is_historical_pivot" class="mt-0.5 rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                    <span>
                        Pivot historique
                        <span class="block text-xs text-slate-500">Point de comparaison historique (ex. Audit/orabank), sans en faire une cible.</span>
                    </span>
                </label>
                <x-ui.textarea label="Notes" wire:model="form.notes" hint="Markdown accepté." />

                <div class="flex gap-2 pt-2">
                    <x-ui.button type="submit">Enregistrer</x-ui.button>
                    <x-ui.button variant="secondary" :href="$form->version ? route('versions.show', $form->version) : route('versions.index')">Annuler</x-ui.button>
                </div>
            </form>
        @endif
    </x-ui.card>
</div>
