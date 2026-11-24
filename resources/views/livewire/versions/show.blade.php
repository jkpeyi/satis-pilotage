<div>
    <x-ui.page-header :title="$version->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('versions.index') }}" wire:navigate class="hover:text-slate-900">Versions</a>
            / <a href="{{ route('products.show', $version->product) }}" wire:navigate class="hover:text-slate-900">{{ $version->product->name }}</a>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-ui.button :href="route('versions.scope', $version)">Périmètre fonctionnel</x-ui.button>
            <x-ui.button variant="secondary" :href="route('versions.architecture', $version)">Architecture</x-ui.button>
            <x-ui.button variant="secondary" :href="route('versions.compare', ['a' => $version->id, 'b' => $version->product->reference_version_id !== $version->id ? $version->product->reference_version_id : null])">Comparer avec…</x-ui.button>
            @if (! $version->isReferenceOfItsProduct() && ! $version->isArchived())
                <x-ui.button variant="secondary" wire:click="makeReference">Définir comme référence</x-ui.button>
            @endif
            <x-ui.button variant="secondary" :href="route('versions.edit', $version)">Modifier</x-ui.button>
            <x-ui.button variant="ghost" wire:click="toggleArchive"
                wire:confirm="{{ $version->isArchived() ? 'Restaurer cette version ?' : 'Archiver cette version ?' }}">
                {{ $version->isArchived() ? 'Restaurer' : 'Archiver' }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @error('reference') <p class="mb-4 text-sm text-red-600">{{ $message }}</p> @enderror

    <div class="mb-6 flex flex-wrap items-center gap-2">
        <x-ui.badge :color="$version->isFuture() ? 'violet' : 'gray'">{{ $version->status->label() }}</x-ui.badge>
        @if ($version->isReferenceOfItsProduct()) <x-ui.badge color="blue">Version de référence</x-ui.badge> @endif
        @if ($version->is_historical_pivot) <x-ui.badge color="blue">Pivot historique</x-ui.badge> @endif
        <x-ui.badge :color="$version->verification_status->color()"
            title="{{ $version->verifier ? 'Par '.$version->verifier->name.' le '.$version->verified_at?->format('d/m/Y') : '' }}">
            {{ $version->verification_status->label() }}
        </x-ui.badge>
        @if ($version->isBaseline()) <x-ui.badge color="violet">Socle (sans client)</x-ui.badge> @endif
        @if ($version->isArchived()) <x-ui.badge>Archivée</x-ui.badge> @endif
        <span class="text-sm text-slate-600">
            Client : {{ $version->customer?->name ?? '—' }}
            · Responsable technique : {{ $version->technicalOwner?->name ?? 'à confirmer' }}
        </span>
    </div>

    @if ($version->notes)
        <x-ui.markdown :text="$version->notes" class="mb-6 max-w-3xl" />
    @endif

    <div class="mb-3 flex justify-end">
        <x-ui.archived-toggle wire:model.live="showArchivedItems">Afficher les éléments archivés</x-ui.archived-toggle>
    </div>

    <div class="space-y-6">
        <x-ui.card title="Dépôts">
            <x-slot:actions>
                <x-ui.button size="sm" wire:click="createRepository">Ajouter un dépôt</x-ui.button>
            </x-slot:actions>

            @if ($repositories->isEmpty())
                <x-ui.empty>Aucun dépôt. Ajoutez le dépôt backend et le dépôt frontend de cette version.</x-ui.empty>
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th>Dépôt</th>
                        <th>Rôle</th>
                        <th>Branches</th>
                        <th>Stack</th>
                        <th></th>
                    </x-slot:head>
                    @foreach ($repositories as $repository)
                        <tr wire:key="repository-{{ $repository->id }}" @class(['opacity-60' => $repository->isArchived()])>
                            <td class="font-mono text-xs">
                                @if ($repository->web_url)
                                    <a href="{{ $repository->web_url }}" target="_blank" rel="noopener" class="text-brand-700 hover:underline">{{ $repository->path }}</a>
                                @else
                                    {{ $repository->path }}
                                @endif
                                @if ($repository->isArchived()) <x-ui.badge>Archivé</x-ui.badge> @endif
                            </td>
                            <td>{{ $repository->role->label() }}</td>
                            <td class="text-xs">
                                <span class="font-medium">prod :</span> <span class="font-mono">{{ $repository->production_branch }}</span>
                                @if ($repository->active_branches)
                                    <div class="mt-1 text-slate-500">{{ implode(', ', $repository->active_branches) }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($stackSupport[$repository->id] as $stackComponent => $support)
                                        <x-ui.badge :color="$support['status']->color()" title="{{ $support['status']->label() }}">
                                            {{ $stackComponent }} {{ $support['version'] }}
                                        </x-ui.badge>
                                    @empty
                                        <span class="text-xs text-slate-400">Non renseignée</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <x-ui.button size="sm" variant="ghost" wire:click="editRepository({{ $repository->id }})">Modifier</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" wire:click="toggleRepositoryArchive({{ $repository->id }})">
                                    {{ $repository->isArchived() ? 'Restaurer' : 'Archiver' }}
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
                <p class="mt-3 text-xs text-slate-500">
                    Couleur de la stack : vert supporté, orange fin de support dans moins de 6 mois, rouge hors support, gris inconnu
                    (à renseigner dans <a href="{{ route('technologies.index') }}" wire:navigate class="underline">Technologies</a>).
                </p>
            @endif
        </x-ui.card>

    </div>

    <x-ui.audit :record="$version" class="mt-6" />

    <x-ui.modal wire:model="showRepositoryForm" :title="$repositoryForm->repository ? 'Modifier le dépôt' : 'Ajouter un dépôt'">
        <form wire:submit="saveRepository" class="space-y-4">
            <x-ui.input label="URL GitLab" type="url" wire:model="repositoryForm.web_url" required class="font-mono"
                placeholder="https://gitlab.satisworld.com/satis/macro-banque-x-api" hint="Adresse complète du projet. Son chemin (ex. satis/macro-banque-x-api) sert de nom au dépôt." />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select label="Rôle" wire:model="repositoryForm.role" :options="$roles" required />
                <x-ui.input label="Branche de production" wire:model="repositoryForm.production_branch" required class="font-mono" />
            </div>
            <x-ui.input label="Branches actives" wire:model="repositoryForm.active_branches" hint="Séparées par des virgules." class="font-mono" />

            <fieldset class="space-y-2">
                <legend class="text-sm font-medium text-slate-700">Stack technique</legend>
                <p class="text-xs text-slate-500">Saisie manuelle en attendant l'intégration GitLab. Ex. php 8.1, laravel 9.52.16, react 17.0.2.</p>
                @foreach ($repositoryForm->stack as $index => $row)
                    <div class="flex items-start gap-2" wire:key="stack-row-{{ $index }}">
                        <x-ui.input wire:model="repositoryForm.stack.{{ $index }}.component" placeholder="Composant" aria-label="Composant" class="font-mono" />
                        <x-ui.input wire:model="repositoryForm.stack.{{ $index }}.version" placeholder="Version" aria-label="Version" class="font-mono" />
                        <x-ui.button size="sm" variant="ghost" wire:click="removeStackRow({{ $index }})" class="mt-1" aria-label="Retirer la ligne">×</x-ui.button>
                    </div>
                @endforeach
                <x-ui.button size="sm" variant="secondary" wire:click="addStackRow">Ajouter un composant</x-ui.button>
            </fieldset>

            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
