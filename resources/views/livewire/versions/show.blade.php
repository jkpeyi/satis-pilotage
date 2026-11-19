<div>
    <x-ui.page-header :title="$version->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('versions.index') }}" wire:navigate class="hover:text-slate-900">Versions</a>
            / <a href="{{ route('products.show', $version->product) }}" wire:navigate class="hover:text-slate-900">{{ $version->product->name }}</a>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-ui.button :href="route('versions.scope', $version)">Périmètre fonctionnel</x-ui.button>
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

        <x-ui.card title="Releases">
            <x-slot:actions>
                @if ($repositories->isNotEmpty())
                    <x-ui.button size="sm" wire:click="createRelease">Ajouter une release</x-ui.button>
                @endif
            </x-slot:actions>

            @if ($releases->isEmpty())
                <x-ui.empty>Aucune release. Une release est un tag d'un dépôt, avec le commit qu'il désigne.</x-ui.empty>
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th>Release</th>
                        <th>Dépôt</th>
                        <th>Commit</th>
                        <th>Date</th>
                        <th></th>
                    </x-slot:head>
                    @foreach ($releases as $release)
                        <tr wire:key="release-{{ $release->id }}" @class(['opacity-60' => $release->isArchived()])>
                            <td class="font-mono text-xs font-medium text-slate-900">
                                {{ $release->name }}
                                @if ($release->isArchived()) <x-ui.badge>Archivée</x-ui.badge> @endif
                            </td>
                            <td class="font-mono text-xs">{{ $release->repository->path }}</td>
                            <td class="font-mono text-xs" title="{{ $release->commit_sha }}">{{ $release->commit_sha ? substr($release->commit_sha, 0, 10) : '—' }}</td>
                            <td>{{ $release->released_on?->format('d/m/Y') ?? '—' }}</td>
                            <td class="whitespace-nowrap text-right">
                                <x-ui.button size="sm" variant="ghost" wire:click="editRelease({{ $release->id }})">Modifier</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" wire:click="toggleReleaseArchive({{ $release->id }})">
                                    {{ $release->isArchived() ? 'Restaurer' : 'Archiver' }}
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>

        <x-ui.card title="Environnements">
            <x-slot:actions>
                <x-ui.button size="sm" wire:click="createEnvironment">Ajouter un environnement</x-ui.button>
            </x-slot:actions>

            @if ($environments->isEmpty())
                <x-ui.empty>Aucun environnement.</x-ui.empty>
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th>Environnement</th>
                        <th>Déployé</th>
                        <th>SGBD</th>
                        <th>PHP</th>
                        <th>Serveur</th>
                        <th></th>
                    </x-slot:head>
                    @foreach ($environments as $environment)
                        <tr wire:key="environment-{{ $environment->id }}" @class(['opacity-60' => $environment->isArchived()])>
                            <td>
                                <div class="font-medium text-slate-900">{{ $environment->name }}</div>
                                <div class="text-xs text-slate-500">{{ $environment->type->label() }}</div>
                                @if ($environment->url)
                                    <a href="{{ $environment->url }}" target="_blank" rel="noopener" class="text-xs text-brand-700 hover:underline">{{ $environment->url }}</a>
                                @endif
                            </td>
                            <td class="text-xs">
                                @php($latest = $environment->deployments->first())
                                <div class="font-mono">{{ $latest?->reference() ?? $environment->deployed_ref ?? '—' }}</div>
                                <div class="text-slate-500">{{ $environment->repository->path ?? '' }}</div>
                                @if ($latest)
                                    <div class="mt-1 flex items-center gap-1 text-slate-500">
                                        {{ $latest->deployed_at->format('d/m/Y') }}
                                        <x-ui.badge :color="$latest->verification_status->color()">{{ $latest->verification_status->label() }}</x-ui.badge>
                                    </div>
                                @endif
                            </td>
                            <td>{{ trim(($environment->database_engine ?? '').' '.($environment->database_version ?? '')) ?: '—' }}</td>
                            <td>{{ $environment->php_version ?? '—' }}</td>
                            <td>{{ $environment->server ?? '—' }}</td>
                            <td class="whitespace-nowrap text-right">
                                @unless ($environment->isArchived())
                                    <x-ui.button size="sm" variant="ghost" wire:click="createDeployment({{ $environment->id }})">Déploiement</x-ui.button>
                                @endunless
                                <x-ui.button size="sm" variant="ghost" wire:click="editEnvironment({{ $environment->id }})">Modifier</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" wire:click="toggleEnvironmentArchive({{ $environment->id }})">
                                    {{ $environment->isArchived() ? 'Restaurer' : 'Archiver' }}
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>

        <x-ui.card title="Déploiements">
            @if ($deployments->isEmpty())
                <x-ui.empty>Aucun déploiement enregistré. Utilisez « Déploiement » sur la ligne d'un environnement.</x-ui.empty>
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th>Date</th>
                        <th>Environnement</th>
                        <th>Release / commit</th>
                        <th>Vérification</th>
                        <th>Preuve</th>
                        <th></th>
                    </x-slot:head>
                    @foreach ($deployments as $deployment)
                        <tr wire:key="deployment-{{ $deployment->id }}" @class(['opacity-60' => $deployment->isArchived()])>
                            <td class="whitespace-nowrap">{{ $deployment->deployed_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $deployment->environment->name }}</td>
                            <td class="font-mono text-xs" title="{{ $deployment->deployedCommit() }}">{{ $deployment->reference() }}</td>
                            <td>
                                <x-ui.badge :color="$deployment->verification_status->color()">{{ $deployment->verification_status->label() }}</x-ui.badge>
                                @if ($deployment->verifier)
                                    <div class="mt-1 text-xs text-slate-500">{{ $deployment->verifier->name }}, {{ $deployment->verified_at?->format('d/m/Y') }}</div>
                                @endif
                            </td>
                            <td class="max-w-xs break-words text-xs text-slate-600">{{ $deployment->evidence ?? '—' }}</td>
                            <td class="whitespace-nowrap text-right">
                                <x-ui.button size="sm" variant="ghost" wire:click="editDeployment({{ $deployment->id }})">Modifier</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" wire:click="toggleDeploymentArchive({{ $deployment->id }})">
                                    {{ $deployment->isArchived() ? 'Restaurer' : 'Archiver' }}
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>
    </div>

    <x-ui.audit :record="$version" class="mt-6" />

    <x-ui.modal wire:model="showRepositoryForm" :title="$repositoryForm->repository ? 'Modifier le dépôt' : 'Ajouter un dépôt'">
        <form wire:submit="saveRepository" class="space-y-4">
            <x-ui.input label="Chemin du dépôt GitLab" wire:model="repositoryForm.path" required class="font-mono" hint="Ex. satis/macro-banque-x-api" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select label="Rôle" wire:model="repositoryForm.role" :options="$roles" required />
                <x-ui.input label="Branche de production" wire:model="repositoryForm.production_branch" required class="font-mono" />
            </div>
            <x-ui.input label="Branches actives" wire:model="repositoryForm.active_branches" hint="Séparées par des virgules." class="font-mono" />
            <x-ui.input label="URL GitLab" type="url" wire:model="repositoryForm.web_url" />

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

    <x-ui.modal wire:model="showEnvironmentForm" :title="$environmentForm->environment ? 'Modifier l\'environnement' : 'Ajouter un environnement'">
        <form wire:submit="saveEnvironment" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select label="Type" wire:model="environmentForm.type" :options="$environmentTypes" required />
                <x-ui.input label="Nom" wire:model="environmentForm.name" required hint="Ex. Production Abidjan" />
            </div>
            <x-ui.input label="URL" type="url" wire:model="environmentForm.url" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select label="Dépôt" wire:model="environmentForm.repository_id" :options="$repositoryOptions" placeholder="—" />
                <x-ui.input label="Référence déployée" wire:model="environmentForm.deployed_ref" hint="Branche, tag ou commit. Mise à jour par le dernier déploiement enregistré." class="font-mono" />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input label="SGBD" wire:model="environmentForm.database_engine" placeholder="MySQL, Oracle…" />
                <x-ui.input label="Version du SGBD" wire:model="environmentForm.database_version" />
                <x-ui.input label="Version PHP" wire:model="environmentForm.php_version" />
            </div>
            <x-ui.input label="Serveur" wire:model="environmentForm.server" />
            <x-ui.textarea label="Notes" wire:model="environmentForm.notes" rows="3" />

            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showReleaseForm" :title="$releaseForm->release ? 'Modifier la release' : 'Ajouter une release'">
        <form wire:submit="saveRelease" class="space-y-4">
            <x-ui.select label="Dépôt" wire:model="releaseForm.repository_id" :options="$repositoryOptions" placeholder="Choisir…" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Nom ou tag" wire:model="releaseForm.name" required class="font-mono" hint="Ex. v2.3.1" />
                <x-ui.input label="Date" type="date" wire:model="releaseForm.released_on" />
            </div>
            <x-ui.input label="Commit" wire:model="releaseForm.commit_sha" class="font-mono" hint="SHA du commit désigné par le tag (7 à 40 caractères)." />
            <x-ui.textarea label="Notes" wire:model="releaseForm.notes" rows="3" />
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showDeploymentForm" :title="$deploymentForm->deployment ? 'Modifier le déploiement' : 'Enregistrer un déploiement'">
        <form wire:submit="saveDeployment" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select label="Environnement" wire:model.live="deploymentForm.environment_id" :options="$environmentOptions" required />
                <x-ui.input label="Date du déploiement" type="datetime-local" wire:model="deploymentForm.deployed_at" required />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select label="Release" wire:model="deploymentForm.release_id" :options="$releaseOptions" placeholder="—" />
                <x-ui.input label="Commit déployé" wire:model="deploymentForm.commit_sha" class="font-mono" hint="Si différent de celui de la release, ou sans release." />
            </div>
            <x-ui.select label="Statut de vérification" wire:model="deploymentForm.verification_status" :options="$verificationStatuses" required />
            <x-ui.input label="Preuve" wire:model="deploymentForm.evidence" hint="Lien vers le pipeline, le ticket ou le contrôle sur le serveur. Jamais de secret." />
            <x-ui.textarea label="Notes" wire:model="deploymentForm.notes" rows="3" />
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
