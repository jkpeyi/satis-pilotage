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
            <x-ui.workflow-diagram :layout="$diagram" />
            <p class="mt-3 text-xs text-slate-500">Rectangle : action · losange : décision (si / sinon) · pastille : fin. Une action sans sortie passe à l'étape suivante ; les boucles sont en pointillé.</p>
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
            <x-ui.select label="Fonctionnalité qui réalise l'étape" wire:model.live="stepForm.feature_id" :options="$featureOptions" placeholder="À préciser" />
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
