<div>
    <x-ui.page-header :title="$form->customer ? $form->customer->name : 'Nouveau client'">
        <x-slot:breadcrumbs>
            <a href="{{ route('customers.index') }}" wire:navigate class="hover:text-slate-900">Clients</a>
            @if ($form->customer?->isArchived()) <x-ui.badge>Archivé</x-ui.badge> @endif
        </x-slot:breadcrumbs>
        @if ($form->customer)
            <x-slot:actions>
                <x-ui.button variant="secondary" :href="route('versions.index', ['customer' => $form->customer->id])">Voir ses versions</x-ui.button>
                <x-ui.button variant="ghost" wire:click="toggleArchive"
                    wire:confirm="{{ $form->customer->isArchived() ? 'Restaurer ce client ?' : 'Archiver ce client ?' }}">
                    {{ $form->customer->isArchived() ? 'Restaurer' : 'Archiver' }}
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <x-ui.card class="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <x-ui.input label="Nom" wire:model="form.name" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select label="Type de client" wire:model="form.type" :options="$types" placeholder="À préciser" />
                <x-ui.input label="Pays" wire:model="form.country" />
            </div>
            <x-ui.textarea label="Notes" wire:model="form.notes" hint="Markdown accepté." />

            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" :href="route('customers.index')">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    @if ($form->customer)
        <x-ui.audit :record="$form->customer" class="mt-6" />
    @endif
</div>
