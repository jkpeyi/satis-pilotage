<div>
    <x-ui.page-header :title="$campaign->title">
        <x-slot:breadcrumbs>
            <a href="{{ route('diagnostics.index') }}" wire:navigate class="hover:text-slate-900">Diagnostics</a>
            / <span class="font-mono">{{ $campaign->displayCode() }}</span>
            @if ($campaign->isArchived()) <x-ui.badge>Archivé</x-ui.badge> @endif
        </x-slot:breadcrumbs>
        <x-slot:actions>
            @unless ($campaign->isArchived())
                <x-ui.button wire:click="openImport">Importer des résultats</x-ui.button>
            @endunless
            <x-ui.button variant="secondary" wire:click="edit">Modifier</x-ui.button>
            <x-ui.button variant="ghost" wire:click="toggleArchive" wire:confirm="{{ $campaign->isArchived() ? 'Restaurer cette campagne ?' : 'Archiver cette campagne ?' }}">
                {{ $campaign->isArchived() ? 'Restaurer' : 'Archiver' }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <x-ui.card title="Référence analysée">
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Dépôt</dt>
                    <dd class="font-mono">{{ $campaign->repository->path }}</dd>
                    <dd class="text-xs text-slate-500">{{ $campaign->repository->version->product->name }} · <a href="{{ route('versions.show', $campaign->repository->version) }}" wire:navigate class="hover:underline">{{ $campaign->repository->version->name }}</a></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Commit</dt>
                    <dd class="break-all font-mono text-xs">
                        @if ($campaign->repository->web_url)
                            <a href="{{ rtrim($campaign->repository->web_url, '/') }}/-/commit/{{ $campaign->commit_sha }}" target="_blank" rel="noopener" class="text-brand-700 hover:underline">{{ $campaign->commit_sha }}</a>
                        @else
                            {{ $campaign->commit_sha }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Date d’analyse</dt>
                    <dd>{{ $campaign->analyzed_on?->format('d/m/Y') ?? 'À confirmer' }}</dd>
                </div>
            </dl>
            @if ($campaign->objective)
                <h3 class="mb-1 mt-5 text-sm font-semibold text-slate-900">Objet</h3>
                <x-ui.markdown :text="$campaign->objective" />
            @endif
            <h3 class="mb-1 mt-5 text-sm font-semibold text-slate-900">Limites</h3>
            @if ($campaign->limitations)
                <x-ui.markdown :text="$campaign->limitations" />
            @else
                <p class="text-sm text-amber-700">Non renseignées : indiquez ce que la campagne n’a pas couvert.</p>
            @endif
        </x-ui.card>

        <x-ui.card title="Tri des résultats">
            <dl class="space-y-2 text-sm">
                @foreach ($findingStatuses as $value => $label)
                    <div class="flex items-center justify-between">
                        <dt><x-ui.badge :color="\App\Enums\FindingStatus::from($value)->color()">{{ $label }}</x-ui.badge></dt>
                        <dd class="tabular-nums">{{ $counts[$value] ?? 0 }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-4 text-xs text-slate-500">Un résultat importé ne devient un problème qu’après vérification : on le retient dans un problème, ou on le rejette comme faux positif en donnant le motif.</p>
        </x-ui.card>
    </div>

    <x-ui.card title="Outils exécutés" class="mb-6">
        @if ($toolRuns->isEmpty())
            <x-ui.empty>Aucun import. Lancez les outils sur le commit {{ $campaign->shortCommit() }} hors de la plateforme, puis importez leurs fichiers de résultats.</x-ui.empty>
        @else
            <x-ui.table>
                <x-slot:head>
                    <th>Outil</th>
                    <th>Format</th>
                    <th>Exécuté le</th>
                    <th>Fichier</th>
                    <th class="text-right">Résultats</th>
                    <th class="text-right">Nouveaux</th>
                    <th class="text-right">Déjà connus</th>
                    <th>Importé</th>
                </x-slot:head>
                @foreach ($toolRuns as $run)
                    <tr wire:key="run-{{ $run->id }}">
                        <td class="font-medium">{{ $run->tool_name }} <span class="text-slate-500">{{ $run->tool_version ?? 'version à confirmer' }}</span></td>
                        <td class="text-xs">{{ $run->format->shortLabel() }}</td>
                        <td>{{ $run->scanned_on->format('d/m/Y') }}</td>
                        <td class="text-xs"><span class="font-mono">{{ $run->file_name }}</span><div class="font-mono text-slate-400" title="SHA-256 du fichier">{{ substr($run->file_sha256, 0, 16) }}…</div></td>
                        <td class="text-right tabular-nums">{{ $run->results_count }}</td>
                        <td class="text-right tabular-nums">{{ $run->new_count }}</td>
                        <td class="text-right tabular-nums">{{ $run->duplicate_count }}</td>
                        <td class="text-xs text-slate-500">{{ $run->created_at->format('d/m/Y H:i') }}@if ($run->creator) · {{ $run->creator->name }}@endif</td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.card title="Résultats" class="mb-6">
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <select wire:model.live="status" aria-label="Statut" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                <option value="">Tous les statuts</option>
                @foreach ($findingStatuses as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
            </select>
            <select wire:model.live="level" aria-label="Niveau" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                <option value="">Tous les niveaux</option>
                @foreach ($levels as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
            </select>
            <select wire:model.live="toolRun" aria-label="Outil" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                <option value="">Tous les imports</option>
                @foreach ($toolRuns as $run) <option value="{{ $run->id }}">{{ $run->toolLabel() }} · {{ $run->file_name }}</option> @endforeach
            </select>
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Règle, fichier, message…" aria-label="Rechercher"
                class="min-w-56 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
        </div>

        @if ($selected !== [])
            <div class="mb-3 flex flex-wrap items-center gap-2 rounded-md bg-brand-50 px-3 py-2 text-sm">
                <span class="font-medium">{{ count($selected) }} sélectionné{{ count($selected) > 1 ? 's' : '' }}</span>
                @if ($status === 'to_verify')
                    <x-ui.button size="sm" wire:click="openCreateIssue">Créer un problème</x-ui.button>
                    <x-ui.button size="sm" variant="secondary" wire:click="openAttach">Retenir dans un problème existant</x-ui.button>
                    <x-ui.button size="sm" variant="secondary" wire:click="openReject">Faux positif</x-ui.button>
                @elseif ($status !== '')
                    <x-ui.button size="sm" variant="secondary" wire:click="reopen" wire:confirm="Remettre ces résultats à vérifier ? Le problème éventuel est conservé.">Remettre à vérifier</x-ui.button>
                @endif
                <x-ui.button size="sm" variant="ghost" wire:click="clearSelection">Désélectionner</x-ui.button>
            </div>
        @elseif ($status === '' && $findings->isNotEmpty())
            <p class="mb-3 text-xs text-slate-500">Filtrez sur un statut pour trier les résultats.</p>
        @endif

        @if ($findings->isEmpty())
            <x-ui.empty>Aucun résultat avec ces filtres.</x-ui.empty>
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="w-8">
                        @if ($status !== '')
                            <input type="checkbox" aria-label="Sélectionner la page" class="rounded border-slate-300"
                                wire:click="selectPage({{ json_encode($findings->pluck('id')->all()) }})" @checked($findings->pluck('id')->map(fn ($id) => (string) $id)->diff($selected)->isEmpty())>
                        @endif
                    </th>
                    <th>Code</th>
                    <th>Niveau</th>
                    <th>Règle et message</th>
                    <th>Emplacement</th>
                    <th>Outil</th>
                    <th>Tri</th>
                </x-slot:head>
                @foreach ($findings as $finding)
                    <tr wire:key="finding-{{ $finding->id }}">
                        <td>
                            @if ($status !== '')
                                <input type="checkbox" value="{{ $finding->id }}" wire:model.live="selected" aria-label="Sélectionner {{ $finding->displayCode() }}" class="rounded border-slate-300">
                            @endif
                        </td>
                        <td class="whitespace-nowrap font-mono text-xs text-slate-500">{{ $finding->displayCode() }}</td>
                        <td><x-ui.badge :color="$finding->level->color()">{{ $finding->level->label() }}</x-ui.badge></td>
                        <td class="max-w-xl text-sm">
                            @if ($finding->rule_id) <div class="font-mono text-xs text-slate-500">{{ $finding->rule_id }}</div> @endif
                            <div class="text-slate-800">{{ $finding->message }}</div>
                            @if ($finding->occurrences > 1) <div class="text-xs text-slate-400">Signalé {{ $finding->occurrences }} fois</div> @endif
                        </td>
                        <td class="max-w-64 break-all pr-4 font-mono text-xs">
                            @php $url = $campaign->fileUrl($finding->path, $finding->line_start, $finding->line_end); @endphp
                            @if ($url)
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="text-brand-700 hover:underline">{{ $finding->location() }}</a>
                            @else
                                {{ $finding->location() ?? '—' }}
                            @endif
                        </td>
                        <td class="text-xs">{{ $finding->toolRun->tool_name }}</td>
                        <td class="text-xs">
                            <x-ui.badge :color="$finding->status->color()">{{ $finding->status->label() }}</x-ui.badge>
                            @if ($finding->issue)
                                <a href="{{ route('issues.show', $finding->issue) }}" wire:navigate class="ml-1 font-mono text-brand-700 hover:underline">{{ $finding->issue->displayCode() }}</a>
                            @endif
                            @if ($finding->triage_note) <div class="mt-1 text-slate-600">{{ $finding->triage_note }}</div> @endif
                            @if ($finding->triager) <div class="text-slate-400">{{ $finding->triager->name }}, {{ $finding->triaged_at?->format('d/m/Y') }}</div> @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
            <div class="mt-4">{{ $findings->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.card title="Tests et couverture" class="mb-6">
        <x-slot:actions>
            <x-ui.button size="sm" variant="secondary" wire:click="createTestRun">Ajouter une exécution</x-ui.button>
        </x-slot:actions>
        @if ($testRuns->isEmpty())
            <x-ui.empty>Aucune exécution de tests. Sans exécution, L4 indique que les tests n’ont pas été mesurés.</x-ui.empty>
        @else
            <x-ui.table>
                <x-slot:head>
                    <th>Outil</th>
                    <th>Exécuté le</th>
                    <th class="text-right">Tests</th>
                    <th class="text-right">Réussis</th>
                    <th class="text-right">En échec</th>
                    <th class="text-right">Ignorés</th>
                    <th class="text-right">Couverture</th>
                    <th>Périmètre</th>
                    <th></th>
                </x-slot:head>
                @foreach ($testRuns as $testRun)
                    <tr wire:key="test-run-{{ $testRun->id }}" @class(['opacity-60' => $testRun->isArchived()])>
                        <td class="font-medium">{{ $testRun->toolLabel() }}</td>
                        <td>{{ $testRun->executed_on->format('d/m/Y') }}</td>
                        <td class="text-right tabular-nums">{{ $testRun->tests_total ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $testRun->tests_passed ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $testRun->tests_failed ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $testRun->tests_skipped ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $testRun->line_coverage !== null ? number_format((float) $testRun->line_coverage, 1, ',', ' ').' %' : 'Non mesurée' }}</td>
                        <td class="max-w-xs text-xs text-slate-600">{{ $testRun->scope ?? '—' }}</td>
                        <td class="whitespace-nowrap text-right">
                            @unless ($testRun->isArchived())
                                <x-ui.button size="sm" variant="ghost" wire:click="editTestRun({{ $testRun->id }})">Modifier</x-ui.button>
                            @endunless
                            <x-ui.button size="sm" variant="ghost" wire:click="toggleTestRunArchive({{ $testRun->id }})">{{ $testRun->isArchived() ? 'Restaurer' : 'Archiver' }}</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.audit :record="$campaign" />

    <x-ui.modal wire:model="showForm" title="Modifier la campagne">
        <form wire:submit="save" class="space-y-4">
            @include('livewire.diagnostics.partials.campaign-fields')
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showImport" title="Importer des résultats d’outil">
        <form wire:submit="import" class="space-y-4">
            <x-ui.select label="Format" wire:model="importForm.format" :options="$formats" required />
            <x-ui.field label="Fichier de résultats" for="import-file" error="importForm.file" required hint="12 Mo au plus. Un même fichier n’est importé qu’une fois ; un résultat déjà connu n’est pas dupliqué. Les extraits de code du fichier ne sont pas conservés.">
                <input id="import-file" type="file" wire:model="importForm.file" accept=".json,.sarif,application/json" class="block w-full text-sm">
                <div wire:loading wire:target="importForm.file" class="mt-1 text-xs text-slate-500">Envoi…</div>
            </x-ui.field>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input label="Outil" wire:model="importForm.tool_name" hint="Vide : lu dans le fichier." />
                <x-ui.input label="Version de l’outil" wire:model="importForm.tool_version" hint="PHPStan et ESLint ne l’écrivent pas." />
                <x-ui.input label="Exécuté le" type="date" wire:model="importForm.scanned_on" required />
            </div>
            <x-ui.input label="Préfixe à retirer des chemins" wire:model="importForm.path_prefix" class="font-mono" placeholder="/builds/satis/macro-api" hint="Dossier où l’outil a tourné, pour garder des chemins relatifs au dépôt (liens GitLab)." />
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="import,importForm.file">Importer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showTestForm" :title="$testForm->testRun ? 'Modifier l’exécution de tests' : 'Ajouter une exécution de tests'">
        <form wire:submit="saveTestRun" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input label="Outil" wire:model="testForm.tool_name" required placeholder="Pest, PHPUnit, Jest…" />
                <x-ui.input label="Version" wire:model="testForm.tool_version" />
                <x-ui.input label="Exécuté le" type="date" wire:model="testForm.executed_on" required />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Rapport JUnit (facultatif)" for="junit-file" error="testForm.junit_file" hint="Remplit les nombres de tests.">
                    <input id="junit-file" type="file" wire:model="testForm.junit_file" accept=".xml" class="block w-full text-sm">
                </x-ui.field>
                <x-ui.field label="Rapport de couverture (facultatif)" for="coverage-file" error="testForm.coverage_file" hint="Clover ou Cobertura : remplit la couverture.">
                    <input id="coverage-file" type="file" wire:model="testForm.coverage_file" accept=".xml" class="block w-full text-sm">
                </x-ui.field>
            </div>
            <div class="grid gap-4 sm:grid-cols-5">
                <x-ui.input label="Tests" wire:model="testForm.tests_total" inputmode="numeric" />
                <x-ui.input label="Réussis" wire:model="testForm.tests_passed" inputmode="numeric" />
                <x-ui.input label="En échec" wire:model="testForm.tests_failed" inputmode="numeric" />
                <x-ui.input label="Ignorés" wire:model="testForm.tests_skipped" inputmode="numeric" />
                <x-ui.input label="Couverture (%)" wire:model="testForm.line_coverage" inputmode="decimal" />
            </div>
            <p class="text-xs text-slate-500">Laissez vide ce qui n’a pas été mesuré : L4 l’indiquera « non mesuré » plutôt que d’inventer une valeur.</p>
            <x-ui.textarea label="Périmètre" wire:model="testForm.scope" rows="2" hint="Suites lancées, modules couverts ou exclus, environnement." />
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showReject" title="Rejeter comme faux positif">
        <form wire:submit="reject" class="space-y-4">
            <p class="text-sm text-slate-600">{{ count($selected) }} résultat{{ count($selected) > 1 ? 's' : '' }}. Le motif est conservé et repris dans L4.</p>
            <x-ui.textarea label="Motif" wire:model="rejectReason" rows="3" required hint="Ex. Code de test, variable toujours initialisée par le framework…" />
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Rejeter</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showAttach" title="Retenir dans un problème existant">
        <form wire:submit="attach" class="space-y-4">
            <p class="text-sm text-slate-600">{{ count($selected) }} résultat{{ count($selected) > 1 ? 's' : '' }}. Le problème doit concerner la version {{ $campaign->repository->version->name }}.</p>
            <x-ui.input label="Problème" wire:model="issueCode" required placeholder="PB-0042" />
            <x-ui.input label="Commentaire" wire:model="attachNote" />
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Retenir</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showCreateIssue" title="Créer un problème à partir des résultats">
        <form wire:submit="createIssue" class="space-y-4">
            <p class="text-sm text-slate-600">{{ count($selected) > 1 ? count($selected).' résultats y seront retenus' : '1 résultat y sera retenu' }}. Champs pré-remplis depuis les résultats : relisez-les.</p>
            <x-ui.field label="Éléments concernés" for="implementation-ids" error="implementationIds" required hint="Éléments du catalogue de la version {{ $campaign->repository->version->name }}. Ctrl ou Cmd pour en choisir plusieurs.">
                <select id="implementation-ids" multiple wire:model="implementationIds" size="6" class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                    @foreach ($implementations as $id => $label) <option value="{{ $id }}">{{ $label }}</option> @endforeach
                </select>
            </x-ui.field>
            @include('livewire.issues.partials.fields', ['prefix' => 'issueForm'])
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Créer le problème</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
