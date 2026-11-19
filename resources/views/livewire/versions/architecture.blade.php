<div>
    <x-ui.page-header title="Architecture" description="Composants techniques de l'application et flux avec les autres composants ou des systèmes externes.">
        <x-slot:breadcrumbs>
            <a href="{{ route('versions.index') }}" wire:navigate class="hover:text-slate-900">Versions</a>
            / <a href="{{ route('versions.show', $version) }}" wire:navigate class="hover:text-slate-900">{{ $version->name }}</a>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-ui.button wire:click="createComponent">Ajouter un composant</x-ui.button>
            @if ($components->isNotEmpty())
                <x-ui.button variant="secondary" wire:click="createIntegration">Ajouter une intégration</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-3 flex justify-end">
        <x-ui.archived-toggle wire:model.live="showArchived" />
    </div>

    <div class="space-y-6">
        <x-ui.card title="Composants">
            @if ($components->isEmpty())
                <x-ui.empty>Aucun composant. Décrivez par exemple l'API, le front web et les traitements batch de cette application.</x-ui.empty>
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th>Composant</th>
                        <th>Type</th>
                        <th>Dépôt</th>
                        <th>Technologie</th>
                        <th>Vérification</th>
                        <th></th>
                    </x-slot:head>
                    @foreach ($components as $item)
                        <tr wire:key="component-{{ $item->id }}" @class(['opacity-60' => $item->isArchived()])>
                            <td>
                                <div class="font-mono text-xs text-slate-500">{{ $item->displayCode() }}</div>
                                <div class="font-medium text-slate-900">{{ $item->name }}</div>
                                @if ($item->evidence_url)
                                    <a href="{{ $item->evidence_url }}" target="_blank" rel="noopener" class="text-xs text-brand-700 hover:underline">Preuve</a>
                                @endif
                                @if ($item->isArchived()) <x-ui.badge>Archivé</x-ui.badge> @endif
                            </td>
                            <td>{{ $item->type->label() }}</td>
                            <td class="font-mono text-xs">{{ $item->repository?->path ?? '—' }}</td>
                            <td>{{ $item->technologyLabel() ?? '—' }}</td>
                            <td><x-ui.badge :color="$item->verification_status->color()">{{ $item->verification_status->label() }}</x-ui.badge></td>
                            <td class="whitespace-nowrap text-right">
                                @unless ($item->isArchived())
                                    <x-ui.button size="sm" variant="ghost" wire:click="createIntegration({{ $item->id }})">Intégration</x-ui.button>
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

        <x-ui.card title="API et intégrations">
            @if ($integrations->isEmpty())
                <x-ui.empty>Aucune intégration. Une intégration part d'un composant vers un autre composant ou vers un système externe.</x-ui.empty>
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th>Intégration</th>
                        <th>Source</th>
                        <th>Cible</th>
                        <th>Protocole</th>
                        <th>Vérification</th>
                        <th></th>
                    </x-slot:head>
                    @foreach ($integrations as $integration)
                        <tr wire:key="integration-{{ $integration->id }}" @class(['opacity-60' => $integration->isArchived()])>
                            <td>
                                <div class="font-mono text-xs text-slate-500">{{ $integration->displayCode() }}</div>
                                @if ($integration->description)
                                    <div class="max-w-xs text-xs text-slate-600">{{ \Illuminate\Support\Str::limit($integration->description, 120) }}</div>
                                @endif
                            </td>
                            <td>{{ $integration->source->name }}</td>
                            <td>
                                @if ($integration->isExternal())
                                    <x-ui.badge color="violet">Externe</x-ui.badge> {{ $integration->external_system }}
                                @else
                                    {{ $integration->target?->name }}
                                    @if ($integration->target && $integration->target->version_id !== $version->id)
                                        <div class="text-xs text-slate-500">{{ $integration->target->version->name }}</div>
                                    @endif
                                @endif
                            </td>
                            <td>{{ $integration->protocol->label() }}</td>
                            <td><x-ui.badge :color="$integration->verification_status->color()">{{ $integration->verification_status->label() }}</x-ui.badge></td>
                            <td class="whitespace-nowrap text-right">
                                @unless ($integration->isArchived())
                                    <x-ui.button size="sm" variant="ghost" wire:click="editIntegration({{ $integration->id }})">Modifier</x-ui.button>
                                @endunless
                                <x-ui.button size="sm" variant="ghost" wire:click="toggleIntegrationArchive({{ $integration->id }})">
                                    {{ $integration->isArchived() ? 'Restaurer' : 'Archiver' }}
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
            <x-ui.input label="Preuve" type="url" wire:model="componentForm.evidence_url" hint="Lien vers le fichier GitLab ou la documentation qui le montre." />
            <x-ui.select label="Statut de vérification" wire:model="componentForm.verification_status" :options="$verificationStatuses" required />
            <x-ui.textarea label="Description" wire:model="componentForm.description" rows="3" />
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showIntegrationForm" :title="$integrationForm->integration ? 'Modifier l’intégration' : 'Ajouter une intégration'">
        <form wire:submit="saveIntegration" class="space-y-4">
            <x-ui.select label="Composant source" wire:model="integrationForm.source_component_id" :options="$sourceOptions" placeholder="Choisir…" required />
            <x-ui.select label="Cible" wire:model.live="integrationForm.target_kind" :options="['component' => 'Un composant du patrimoine', 'external' => 'Un système externe']" required />
            @if ($integrationForm->target_kind === 'component')
                <x-ui.select label="Composant cible" wire:model="integrationForm.target_component_id" :options="$targetOptions" placeholder="Choisir…" required />
            @else
                <x-ui.input label="Système externe" wire:model="integrationForm.external_system" required hint="Ex. Centrale des risques, opérateur de paiement mobile" />
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select label="Protocole" wire:model="integrationForm.protocol" :options="$protocols" required />
                <x-ui.select label="Statut de vérification" wire:model="integrationForm.verification_status" :options="$verificationStatuses" required />
            </div>
            <x-ui.input label="Preuve" type="url" wire:model="integrationForm.evidence_url" hint="Lien vers le code client de l'API, le contrat d'interface ou la documentation. Jamais de secret." />
            <x-ui.textarea label="Description" wire:model="integrationForm.description" rows="3" hint="Données échangées, fréquence, sens." />
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
