<div>
    <x-ui.page-header :title="$form->product ? 'Modifier le produit' : 'Nouveau produit'">
        <x-slot:breadcrumbs>
            <a href="{{ route('products.index') }}" wire:navigate class="hover:text-slate-900">Produits</a>
            @if ($form->product)
                / <a href="{{ route('products.show', $form->product) }}" wire:navigate class="hover:text-slate-900">{{ $form->product->name }}</a>
            @endif
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    <x-ui.card class="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input label="Code" wire:model="form.code" hint="Ex. MACRO" required class="font-mono uppercase" />
                <x-ui.input label="Nom" wire:model="form.name" required class="sm:col-span-2" />
            </div>
            <x-ui.textarea label="Description" wire:model="form.description" hint="Markdown accepté." />

            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" :href="$form->product ? route('products.show', $form->product) : route('products.index')">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
