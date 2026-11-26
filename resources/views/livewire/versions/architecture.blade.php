<div>
    <div class="space-y-6">
        <x-ui.card title="Composants">
            <x-slot:actions>
                <x-ui.button size="sm" wire:click="createComponent">Ajouter un composant</x-ui.button>
            </x-slot:actions>
            @if ($components->isEmpty())
                <x-ui.empty>Aucun composant. Décrivez par exemple l'API, le front web et les traitements batch de cette application.</x-ui.empty>
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th>Composant</th>
                        <th>Type</th>
                        <th>Dépôt</th>
                        <th>Technologie</th>
                        <th>Note</th>
                        <th></th>
                    </x-slot:head>
                    @foreach ($components as $item)
                        <tr wire:key="component-{{ $item->id }}" @class(['opacity-60' => $item->isArchived()])>
                            <td>
                                <div class="font-mono text-xs text-slate-500">{{ $item->displayCode() }}</div>
                                <div class="font-medium text-slate-900">{{ $item->name }}</div>
                                @if ($item->isArchived()) <x-ui.badge>Archivé</x-ui.badge> @endif
                            </td>
                            <td>{{ $item->type->label() }}</td>
                            <td class="font-mono text-xs">{{ $item->repository?->path ?? '—' }}</td>
                            <td>{{ $item->technologyLabel() ?? '—' }}</td>
                            <td class="max-w-xs text-xs text-slate-600">{{ $item->description !== null ? \Illuminate\Support\Str::limit($item->description, 160) : '—' }}</td>
                            <td class="whitespace-nowrap text-right">
                                @unless ($item->isArchived())
                                    <x-ui.button size="sm" variant="ghost" wire:click="editComponent({{ $item->id }})">Modifier</x-ui.button>
                                @endunless
                                <x-ui.button size="sm" variant="ghost" wire:click="toggleComponentArchive({{ $item->id }})">
                                    {{ $item->isArchived() ? 'Restaurer' : 'Archiver' }}
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>
    </div>

    <x-ui.modal wire:model="showComponentForm" :title="$componentForm->editedComponent ? 'Modifier le composant' : 'Ajouter un composant'">
        <form wire:submit="saveComponent" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Nom" wire:model="componentForm.name" required hint="Ex. API clients, Batch de clôture" />
                <x-ui.select label="Type" wire:model="componentForm.type" :options="$componentTypes" required />
            </div>
            <x-ui.select label="Dépôt" wire:model="componentForm.repository_id" :options="$repositoryOptions" placeholder="—" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Technologie" wire:model="componentForm.technology" placeholder="Laravel, React, Oracle…" />
                <x-ui.input label="Version" wire:model="componentForm.technology_version" class="font-mono" placeholder="9.52.16" />
            </div>
            <x-ui.textarea label="Note" wire:model="componentForm.description" rows="3" hint="Markdown." />
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
