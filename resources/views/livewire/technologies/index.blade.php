<div>
    <x-ui.page-header title="Technologies" description="Dates de fin de support utilisées pour signaler les stacks obsolètes. Les technologies saisies sur les dépôts, composants et environnements s'ajoutent ici automatiquement.">
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="sync">Récupérer depuis les versions</x-ui.button>
            <x-ui.button wire:click="create">Ajouter un cycle</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-center gap-4">
        @if ($missing > 0)
            <p class="text-sm text-amber-800">{{ $missing }} cycle(s) sans date de fin de support : renseignez-la pour suivre l'obsolescence.</p>
        @endif
        <x-ui.archived-toggle wire:model.live="onlyMissing">Seulement les dates à renseigner</x-ui.archived-toggle>
    </div>

    @if ($technologies->isEmpty())
        <x-ui.empty>
            Aucun cycle renseigné. Ajoutez par exemple php 8.1 (fin de support 31/12/2025) ou laravel 9.
        </x-ui.empty>
    @else
        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($technologies as $componentName => $cycles)
                <x-ui.card :title="$componentName" wire:key="component-{{ $componentName }}">
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($cycles as $technology)
                            <li class="flex items-center justify-between gap-4 py-2" wire:key="technology-{{ $technology->id }}">
                                <span class="font-mono">{{ $technology->cycle }}</span>
                                <span @class(['flex-1', 'text-slate-500' => $technology->end_of_support, 'font-medium text-amber-800' => ! $technology->end_of_support])>{{ $technology->end_of_support?->format('d/m/Y') ?? 'À renseigner' }}</span>
                                <x-ui.badge :color="$statuses[$technology->id]->color()">{{ $statuses[$technology->id]->label() }}</x-ui.badge>
                                <span class="whitespace-nowrap">
                                    <x-ui.button size="sm" variant="ghost" wire:click="edit({{ $technology->id }})">Modifier</x-ui.button>
                                    <x-ui.button size="sm" variant="ghost" wire:click="delete({{ $technology->id }})" wire:confirm="Supprimer ce cycle ?">Supprimer</x-ui.button>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endforeach
        </div>
    @endif

    <x-ui.modal wire:model="showForm" :title="$form->technology ? 'Modifier le cycle' : 'Ajouter un cycle'">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Composant" wire:model="form.componentName" required placeholder="php, laravel, react, node, mysql…" class="font-mono" />
                <x-ui.input label="Cycle" wire:model="form.cycle" required placeholder="8.1" class="font-mono" hint="Une version correspond au cycle le plus long par lequel elle commence : 9.52.16 → 9." />
            </div>
            <x-ui.input label="Fin de support" type="date" wire:model="form.end_of_support" />
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
