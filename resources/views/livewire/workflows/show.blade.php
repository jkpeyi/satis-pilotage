<div>
    <x-ui.page-header :title="$workflow->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('workflows.index') }}" wire:navigate class="hover:text-slate-900">Workflows</a>
            / {{ $workflow->product->name }} / <span class="font-mono">{{ $workflow->displayCode() }}</span>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-ui.button wire:click="createStep">Ajouter une étape</x-ui.button>
            <x-ui.button variant="secondary" wire:click="edit">Modifier</x-ui.button>
            <x-ui.button variant="ghost" wire:click="toggleArchive"
                wire:confirm="{{ $workflow->isArchived() ? 'Restaurer ce workflow ?' : 'Archiver ce workflow ?' }}">
                {{ $workflow->isArchived() ? 'Restaurer' : 'Archiver' }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($workflow->isArchived()) <x-ui.badge class="mb-4">Archivé</x-ui.badge> @endif

    @if ($workflow->description)
        <x-ui.markdown :text="$workflow->description" class="mb-6 max-w-3xl" />
    @endif

    @if ($diagram['nodes'] !== [])
        <x-ui.card title="Schéma" class="mb-6">
            @if ($graph->warnings !== [])
                <ul class="mb-4 space-y-1 rounded-md bg-amber-50 p-3 text-sm text-amber-900">
                    @foreach ($graph->warnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($coverage->versions->isNotEmpty())
                <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                    <label for="coverage-version" class="text-slate-600">Colorer selon la version</label>
                    <select id="coverage-version" wire:model.live="coverageVersion" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm shadow-sm">
                        <option value="">Aucune</option>
                        @foreach ($coverage->versions as $version) <option value="{{ $version->id }}">{{ $version->name }}</option> @endforeach
                    </select>
                    @if ($nodeColors !== [])
                        <span class="flex flex-wrap gap-3 text-xs text-slate-600">
                            <span><span class="inline-block h-2.5 w-2.5 rounded-sm border-2 border-emerald-600 bg-emerald-50"></span> opérationnelle</span>
                            <span><span class="inline-block h-2.5 w-2.5 rounded-sm border-2 border-amber-600 bg-amber-50"></span> partielle, non testée ou inconnue</span>
                            <span><span class="inline-block h-2.5 w-2.5 rounded-sm border-2 border-red-600 bg-red-50"></span> non rattachée, absente ou défaillante</span>
                            <span><span class="inline-block h-2.5 w-2.5 rounded-sm border-2 border-dashed border-slate-400"></span> fonctionnalité à préciser</span>
                        </span>
                    @endif
                </div>
            @endif
            <x-ui.workflow-diagram :layout="$diagram" :colors="$nodeColors" />
            <p class="mt-3 text-xs text-slate-500">Rectangle : action · losange : décision (si / sinon) · pastille : fin. Une action sans sortie passe à l'étape suivante ; les boucles sont en pointillé.</p>
        </x-ui.card>
    @endif

    @if ($coverage->steps->isNotEmpty() && $coverage->versions->isNotEmpty())
        <x-ui.card title="Couverture par version" class="mb-6">
            <p class="mb-3 text-xs text-slate-500">Chaque étape prend, dans chaque version, l’état de la fonctionnalité qui la réalise (périmètre fonctionnel de la version). Rien n’est saisi ici : le workflow reste le processus standard du produit.</p>
            <div class="overflow-x-auto">
                <x-ui.table>
                    <x-slot:head>
                        <th>Étape</th>
                        @foreach ($coverage->versions as $version)
                            <th class="min-w-36">{{ $version->name }}@if ($version->status === \App\Enums\VersionStatus::Future) <span class="font-normal normal-case text-slate-400">(perspective)</span>@endif</th>
                        @endforeach
                    </x-slot:head>
                    @foreach ($coverage->steps as $step)
                        <tr wire:key="coverage-{{ $step->id }}">
                            <td class="text-sm">
                                <span class="tabular-nums text-slate-500">{{ $step->position }}.</span> {{ $step->name }}
                                @if ($step->feature) <div class="text-xs text-slate-500">{{ $step->feature->name }}</div> @endif
                            </td>
                            @foreach ($coverage->versions as $version)
                                @php $cell = $coverage->cell($step, $version); @endphp
                                <td>
                                    @if ($cell instanceof \App\Enums\ImplementationStatus)
                                        <x-ui.badge :color="$cell->color()">{{ $cell->label() }}</x-ui.badge>
                                    @elseif ($cell === \App\Queries\WorkflowCoverage::NOT_ATTACHED)
                                        <x-ui.badge color="red">Non rattachée</x-ui.badge>
                                    @else
                                        <span class="text-xs text-slate-400">{{ \App\Queries\WorkflowCoverage::label($cell) }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="bg-slate-50">
                        <td class="text-xs font-semibold text-slate-700">Synthèse</td>
                        @foreach ($coverage->versions as $version)
                            @php $summary = $coverage->summary($version); @endphp
                            <td class="text-xs text-slate-700">
                                {{ $summary['operational'] }} / {{ $summary['steps'] }} opérationnelle{{ $summary['operational'] > 1 ? 's' : '' }}
                                @if ($summary['blocking'] > 0) <div class="font-medium text-red-700">{{ $summary['blocking'] }} bloquante{{ $summary['blocking'] > 1 ? 's' : '' }}</div> @endif
                                @if ($summary['undefined'] > 0) <div class="text-slate-500">{{ $summary['undefined'] }} à préciser</div> @endif
                            </td>
                        @endforeach
                    </tr>
                </x-ui.table>
            </div>
            <p class="mt-2 text-xs text-slate-500">Bloquante : la fonctionnalité n’est pas rattachée à la version, ou y est absente ou défaillante ; le processus ne peut pas se dérouler jusqu’au bout.</p>
        </x-ui.card>
    @endif

    <x-ui.card title="Étapes">
        <x-slot:actions>
            <x-ui.archived-toggle wire:model.live="showArchivedSteps">Afficher les étapes archivées</x-ui.archived-toggle>
        </x-slot:actions>

        @if ($steps->isEmpty())
            <x-ui.empty>Aucune étape. Décrivez le processus dans l'ordre, et indiquez pour chaque étape la fonctionnalité qui la réalise.</x-ui.empty>
        @else
            <x-ui.table>
                <x-slot:head>
                    <th class="w-10">N°</th>
                    <th>Étape</th>
                    <th>Acteur</th>
                    <th>Fonctionnalité</th>
                    <th>Règles métier</th>
                    <th>Sorties</th>
                    <th></th>
                </x-slot:head>
                @foreach ($steps as $step)
                    <tr wire:key="step-{{ $step->id }}" @class(['opacity-60' => $step->isArchived()])>
                        <td class="tabular-nums text-slate-500">{{ $step->isArchived() ? '—' : $step->position }}</td>
                        <td>
                            <div class="font-medium text-slate-900">
                                {{ $step->name }}
                                @if ($step->kind !== \App\Enums\WorkflowStepKind::Action)
                                    <x-ui.badge :color="$step->kind === \App\Enums\WorkflowStepKind::Decision ? 'amber' : 'gray'">{{ $step->kind === \App\Enums\WorkflowStepKind::Decision ? 'Décision' : 'Fin' }}</x-ui.badge>
                                @endif
                            </div>
                            @if ($step->description)
                                <x-ui.markdown :text="$step->description" class="mt-1 text-xs text-slate-600" />
                            @endif
                        </td>
                        <td class="text-slate-700">{{ $step->actor ?? '—' }}</td>
                        <td class="text-xs">
                            @if ($step->feature)
                                <div class="font-mono text-slate-500">{{ $step->feature->displayCode() }}</div>
                                <div class="text-slate-700">{{ $featureLabels[$step->feature_id] ?? $step->feature->name }}</div>
                            @elseif ($step->kind !== \App\Enums\WorkflowStepKind::Action)
                                <span class="text-slate-400">Sans objet</span>
                            @else
                                <span class="text-slate-400">À préciser</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @forelse ($step->businessRules as $rule)
                                    <a href="{{ route('business-rules.show', $rule) }}" wire:navigate title="{{ $rule->title }} — {{ $rule->implementation->version->name }}">
                                        <x-ui.badge color="blue">{{ $rule->displayCode() }}</x-ui.badge>
                                    </a>
                                @empty
                                    <span class="text-xs text-slate-400">—</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="text-xs text-slate-700">{{ $step->isArchived() ? '—' : ($graph->exitsLabel($step->id) ?: '—') }}</td>
                        <td class="whitespace-nowrap text-right">
                            @unless ($step->isArchived())
                                <x-ui.button size="sm" variant="ghost" wire:click="moveStep({{ $step->id }}, -1)" aria-label="Monter">↑</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" wire:click="moveStep({{ $step->id }}, 1)" aria-label="Descendre">↓</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" wire:click="editStep({{ $step->id }})">Modifier</x-ui.button>
                            @endunless
                            <x-ui.button size="sm" variant="ghost" wire:click="toggleStepArchive({{ $step->id }})">
                                {{ $step->isArchived() ? 'Restaurer' : 'Archiver' }}
                            </x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.audit :record="$workflow" class="mt-6" />

    <x-ui.modal wire:model="showForm" title="Modifier le workflow">
        <form wire:submit="save" class="space-y-4">
            <x-ui.select label="Produit" wire:model="form.product_id" :options="$products" required />
            <x-ui.input label="Nom" wire:model="form.name" required />
            <x-ui.textarea label="Description" wire:model="form.description" rows="4" hint="Markdown accepté." />
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showStepForm" :title="$stepForm->step ? 'Modifier l’étape' : 'Ajouter une étape'">
        <form wire:submit="saveStep" class="space-y-4">
            <x-ui.select label="Type" wire:model.live="stepForm.kind" :options="$kinds" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Étape" wire:model="stepForm.name" required :hint="$stepForm->kind === 'decision' ? 'Formulez la question, ex. Réclamation fondée ?' : 'Ex. Qualification de la réclamation'" />
                <x-ui.input label="Acteur" wire:model="stepForm.actor" hint="Un rôle ou une équipe, jamais le nom d'une personne." />
            </div>
            <x-ui.field label="Fonctionnalité qui réalise l'étape" for="feature-search" error="featureSearch">
                @if ($chosenFeature)
                    <div class="flex items-start justify-between gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        <span>
                            <span class="font-medium text-slate-900">{{ $chosenFeature['label'] }}</span>
                            <span class="mt-1 block text-xs text-slate-500">{{ implode(' · ', $chosenFeature['versions']) }}</span>
                        </span>
                        <button type="button" wire:click="clearFeature" class="text-xs text-slate-500 hover:text-slate-900">Changer</button>
                    </div>
                @else
                    <input id="feature-search" type="text" wire:model.live.debounce.250ms="featureSearch" placeholder="Rechercher dans le catalogue… (vide : à préciser)"
                        class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/30">
                    @if (trim($featureSearch) !== '')
                        <div class="mt-1 rounded-md border border-slate-200 bg-white text-sm shadow-sm">
                            @foreach ($featureSuggestions as $suggestion)
                                <button type="button" wire:click="chooseFeature({{ $suggestion['id'] }})" wire:key="feature-suggestion-{{ $suggestion['id'] }}"
                                    class="flex w-full items-center gap-2 border-b border-slate-100 px-3 py-1.5 text-left hover:bg-slate-50">
                                    <span class="min-w-0 flex-1 truncate">
                                        <span @class(['font-semibold text-slate-900' => $suggestion['exact'], 'text-slate-800' => ! $suggestion['exact']])>{{ $suggestion['name'] }}</span>
                                        <span class="text-xs text-slate-400">· {{ $suggestion['path'] }}</span>
                                    </span>
                                    @if (! $suggestion['in_product']) <x-ui.badge>hors produit</x-ui.badge> @endif
                                </button>
                            @endforeach
                            @if (! collect($featureSuggestions)->contains('exact', true))
                                <div class="flex flex-wrap items-center gap-2 px-3 py-2">
                                    <span class="text-xs text-slate-600">Créer « {{ $featureSearch }} » dans</span>
                                    <select wire:model="newFeatureModuleId" aria-label="Module de la nouvelle fonctionnalité" class="min-w-0 flex-1 rounded-md border border-slate-300 px-2 py-1 text-xs">
                                        <option value="">Choisir le module…</option>
                                        @foreach ($moduleOptions as $id => $label) <option value="{{ $id }}">{{ $label }}</option> @endforeach
                                    </select>
                                    <x-ui.button size="sm" variant="secondary" wire:click="createFeature">Créer</x-ui.button>
                                </div>
                                @error('newFeatureModuleId') <p class="px-3 pb-2 text-xs text-red-600">{{ $message }}</p> @enderror
                            @endif
                        </div>
                    @endif
                @endif
            </x-ui.field>
            <x-ui.textarea label="Description" wire:model="stepForm.description" rows="3" hint="Markdown accepté." />

            @if ($stepForm->kind === 'decision')
                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium text-slate-700">Sorties (si / sinon)</legend>
                    <p class="text-xs text-slate-500">Une ligne par cas : la condition, puis l'étape où elle mène. Créez d'abord les étapes cibles si besoin.</p>
                    @foreach ($stepForm->transitions as $index => $transition)
                        <div class="flex items-start gap-2" wire:key="transition-{{ $index }}">
                            <x-ui.input wire:model="stepForm.transitions.{{ $index }}.label" placeholder="Si… (ex. Oui)" aria-label="Condition" />
                            <x-ui.select wire:model="stepForm.transitions.{{ $index }}.target_step_id" :options="$targetOptions" placeholder="Aller à…" aria-label="Étape cible" />
                            <x-ui.button size="sm" variant="ghost" wire:click="removeTransition({{ $index }})" class="mt-1" aria-label="Retirer la sortie">×</x-ui.button>
                        </div>
                    @endforeach
                    <x-ui.button size="sm" variant="secondary" wire:click="addTransition">Ajouter une sortie</x-ui.button>
                </fieldset>
            @elseif ($stepForm->kind === 'action')
                <x-ui.select label="Étape suivante" wire:model="stepForm.transitions.0.target_step_id" :options="$targetOptions"
                    placeholder="La suivante dans l'ordre" hint="À choisir seulement pour sauter des étapes ou revenir en arrière." />
            @endif
            @error('stepForm.transitions') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @foreach ($errors->get('stepForm.transitions.*') as $messages)
                <p class="text-sm text-red-600">{{ $messages[0] }}</p>
            @endforeach

            <fieldset class="space-y-2">
                <legend class="text-sm font-medium text-slate-700">Règles métier qui s'appliquent</legend>
                @if ($stepForm->feature_id === '')
                    <p class="text-xs text-slate-500">Choisissez d'abord la fonctionnalité : les règles proposées sont celles de cette fonctionnalité et de ses modules, dans les versions du produit.</p>
                @elseif ($candidateRules->isEmpty())
                    <p class="text-xs text-slate-500">Aucune règle métier sur cette fonctionnalité ni sur ses modules.</p>
                @else
                    <div class="max-h-56 space-y-1 overflow-y-auto rounded-md border border-slate-200 p-2">
                        @foreach ($candidateRules as $rule)
                            <label class="flex items-start gap-2 text-sm" wire:key="candidate-rule-{{ $rule->id }}">
                                <input type="checkbox" value="{{ $rule->id }}" wire:model="stepForm.business_rule_ids" class="mt-0.5 rounded border-slate-300 text-brand-700">
                                <span>
                                    <span class="font-mono text-xs text-slate-500">{{ $rule->displayCode() }}</span> {{ $rule->title }}
                                    <span class="block text-xs text-slate-500">{{ $rule->implementation->version->name }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
                @error('stepForm.business_rule_ids') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </fieldset>
            @error('stepForm.save') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
