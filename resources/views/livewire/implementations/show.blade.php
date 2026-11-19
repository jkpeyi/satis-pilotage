<div>
    <x-ui.page-header :title="$item->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('versions.show', $implementation->version) }}" wire:navigate class="hover:text-slate-900">{{ $implementation->version->name }}</a>
            / <a href="{{ route('versions.scope', $implementation->version) }}" wire:navigate class="hover:text-slate-900">Périmètre</a>
            / {{ $item->typeLabel() }}@if ($path) · {{ implode(' › ', $path) }}@endif
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-ui.badge :color="$implementation->status->color()" class="text-sm">{{ $implementation->status->label() }}</x-ui.badge>
            <x-ui.button variant="secondary" :href="route('catalog.index', ['item' => ($implementation->module_id ? 'module:' : 'feature:').$item->id])">Voir dans le catalogue</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($implementation->isArchived())
        <p class="mb-6 rounded-md border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-600">Cet élément est détaché de la version : sa fiche reste consultable.</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            <x-ui.card title="Description standard">
                @if ($item->standard_description)
                    <x-ui.markdown :text="$item->standard_description" />
                @else
                    <p class="text-sm text-slate-500">Pas encore de description standard dans le catalogue. L'écart se décrit par rapport à elle.</p>
                @endif
            </x-ui.card>

            <x-ui.card title="Évaluation dans cette version">
                <form wire:submit="saveAssessment" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select label="Présence" wire:model.live="assessment.presence" :options="$presences" required />
                        <x-ui.select label="Fonctionnement" wire:model="assessment.operation" :options="$operations" required
                            :disabled="$assessment->presence !== 'present'"
                            :hint="$assessment->presence !== 'present' ? 'Sans objet : l’élément n’est pas présent.' : null" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_14rem]">
                        <x-ui.input label="Commentaire d'évaluation" wire:model="assessment.assessment_note"
                            hint="Repris dans l'historique si l'état change." />
                        <x-ui.select label="Vérification" wire:model="assessment.verification_status" :options="$verificationStatuses" required
                            :hint="$implementation->verifier ? 'Par '.$implementation->verifier->name.' le '.$implementation->verified_at?->format('d/m/Y') : null" />
                    </div>
                    <x-ui.textarea label="Écart" wire:model="assessment.deviation" rows="6"
                        hint="En quoi cette version diffère de la description standard. Markdown. Laisser vide si conforme." />
                    <x-ui.button type="submit">Enregistrer l'évaluation</x-ui.button>
                </form>

                @if ($implementation->deviation)
                    <div class="mt-5 border-t border-slate-200 pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Écart enregistré</p>
                        <x-ui.markdown :text="$implementation->deviation" />
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card title="Règles métier">
                <x-slot:actions>
                    <x-ui.button size="sm" wire:click="createRule">Nouvelle règle</x-ui.button>
                </x-slot:actions>
                @if ($rules->isEmpty())
                    <p class="text-sm text-slate-500">Aucune règle métier pour cet élément dans cette version.</p>
                @else
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($rules as $rule)
                            <li class="flex items-center justify-between gap-3 py-2" wire:key="rule-{{ $rule->id }}">
                                <a href="{{ route('business-rules.show', $rule) }}" wire:navigate class="min-w-0 truncate hover:text-brand-700">
                                    <span class="font-mono text-xs text-slate-500">{{ $rule->displayCode() }}</span>
                                    <span class="font-medium text-slate-800">{{ $rule->title }}</span>
                                </a>
                                <span class="flex shrink-0 items-center gap-2 text-xs text-slate-500">
                                    @if ($rule->code_snippets_count) {{ $rule->code_snippets_count }} extrait(s) @endif
                                    <x-ui.badge>{{ $rule->source->label() }}</x-ui.badge>
                                    <x-ui.badge :color="$rule->status === \App\Enums\RuleStatus::Validated ? 'green' : 'amber'">{{ $rule->status->label() }}</x-ui.badge>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card title="Problèmes">
                <x-slot:actions>
                    <x-ui.button size="sm" wire:click="createIssue">Nouveau problème</x-ui.button>
                </x-slot:actions>
                @if ($issues->isEmpty())
                    <p class="text-sm text-slate-500">Aucun problème lié.</p>
                @else
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($issues as $issue)
                            <li class="flex items-center justify-between gap-3 py-2" wire:key="issue-{{ $issue->id }}">
                                <a href="{{ route('issues.show', $issue) }}" wire:navigate class="min-w-0 truncate hover:text-brand-700">
                                    <span class="font-mono text-xs text-slate-500">{{ $issue->displayCode() }}</span>
                                    <span class="font-medium text-slate-800">{{ $issue->title }}</span>
                                </a>
                                <span class="flex shrink-0 items-center gap-2">
                                    <x-ui.severity-badge :severity="$issue->severity" />
                                    <x-ui.badge>{{ $issue->status->label() }}</x-ui.badge>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form wire:submit="linkIssueByCode" class="mt-4 flex flex-wrap items-start gap-2 border-t border-slate-200 pt-4">
                    <x-ui.input wire:model="issueCode" placeholder="PB-0042" aria-label="Code du problème à lier" class="w-32 font-mono" />
                    <x-ui.button type="submit" size="sm" variant="secondary" class="mt-1">Lier un problème existant</x-ui.button>
                </form>

                @if ($knownIssues->isNotEmpty())
                    <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3">
                        <p class="text-sm font-medium text-amber-900">Problèmes connus sur cet élément dans d'autres versions</p>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($knownIssues as $known)
                                <li class="flex items-center justify-between gap-3" wire:key="known-{{ $known->id }}">
                                    <a href="{{ route('issues.show', $known) }}" wire:navigate class="min-w-0 truncate text-amber-900 hover:underline">
                                        <span class="font-mono text-xs">{{ $known->displayCode() }}</span> {{ $known->title }}
                                    </a>
                                    <x-ui.button size="sm" variant="secondary" wire:click="linkIssue({{ $known->id }})">Concerne aussi cette version</x-ui.button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-ui.card>
        </div>

        <aside class="min-w-0 space-y-6">
            <x-ui.card title="Historique des états">
                @if ($implementation->statusChanges->isEmpty())
                    <p class="text-sm text-slate-500">Aucun changement depuis le rattachement.</p>
                @else
                    <ol class="space-y-3 text-sm">
                        @foreach ($implementation->statusChanges->reverse() as $change)
                            <li class="border-l-2 border-slate-200 pl-3">
                                <div class="flex flex-wrap items-center gap-1">
                                    @if ($change->from_status)
                                        <x-ui.badge :color="$change->from_status->color()">{{ $change->from_status->label() }}</x-ui.badge> →
                                    @endif
                                    <x-ui.badge :color="$change->to_status->color()">{{ $change->to_status->label() }}</x-ui.badge>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ $change->created_at?->format('d/m/Y à H:i') }} · {{ $change->user->name ?? 'Système' }}</p>
                                @if ($change->note) <p class="mt-1 text-slate-700">{{ $change->note }}</p> @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card>

            <x-ui.card>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-xs text-slate-500">Version</dt><dd>{{ $implementation->version->product->name }} · {{ $implementation->version->name }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Client</dt><dd>{{ $implementation->version->customer?->name ?? 'Socle (sans client)' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Dernière évaluation</dt>
                        <dd>{{ $implementation->assessed_at?->format('d/m/Y') ?? '—' }}@if ($implementation->assessor) par {{ $implementation->assessor->name }}@endif</dd></div>
                </dl>
                <x-ui.audit :record="$implementation" class="mt-4" />
            </x-ui.card>
        </aside>
    </div>

    <x-ui.modal wire:model="showRuleForm" title="Nouvelle règle métier">
        <form wire:submit="saveRule" class="space-y-4">
            <x-ui.input label="Intitulé" wire:model="ruleForm.title" required placeholder="Ex. Plafond du taux d'intérêt" />
            <x-ui.textarea label="Description" wire:model="ruleForm.description" rows="5" required hint="Markdown accepté." />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select label="Source" wire:model="ruleForm.source" :options="$sources" required />
                <x-ui.select label="Statut" wire:model="ruleForm.status" :options="$ruleStatuses" required />
            </div>
            <p class="text-xs text-slate-500">Les extraits de code s'ajoutent sur la page de la règle.</p>
            <div class="flex gap-2">
                <x-ui.button type="submit">Créer la règle</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showIssueForm" title="Nouveau problème">
        <form wire:submit="saveIssue" class="space-y-4">
            @include('livewire.issues.partials.fields', ['prefix' => 'issueForm'])
            <div class="flex gap-2">
                <x-ui.button type="submit">Créer le problème</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
