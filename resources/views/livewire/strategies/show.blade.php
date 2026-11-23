<div>
    <x-ui.page-header :title="$strategy->title">
        <x-slot:breadcrumbs>
            <a href="{{ route('strategies.index') }}" wire:navigate class="hover:text-slate-900">Stratégies</a>
            / {{ $strategy->product->name }} / <span class="font-mono">{{ $strategy->displayCode() }}</span>
            @if ($strategy->isArchived()) <x-ui.badge>Archivée</x-ui.badge> @endif
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="edit">Modifier</x-ui.button>
            <x-ui.button variant="ghost" wire:click="toggleArchive" wire:confirm="{{ $strategy->isArchived() ? 'Restaurer cette stratégie ?' : 'Archiver cette stratégie ?' }}">
                {{ $strategy->isArchived() ? 'Restaurer' : 'Archiver' }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @php
        $status = $strategy->approval_status->value;
        $optionsActive = $strategy->options->whereNull('archived_at');
    @endphp

    <x-ui.card class="mb-6">
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm text-slate-600">Validation :</span>
            <x-ui.badge :color="$strategy->approval_status->color()">{{ $strategy->approval_status->label() }}</x-ui.badge>
            @if ($strategy->approver)
                <span class="text-sm text-slate-600">par {{ $strategy->approver->name }} le {{ $strategy->approved_at?->format('d/m/Y') }}</span>
            @endif
            <span class="ml-auto flex flex-wrap gap-2">
                @if ($status === 'draft')
                    <x-ui.button size="sm" wire:click="changeApproval('in_review')">Soumettre en revue</x-ui.button>
                @elseif ($status === 'in_review')
                    <x-ui.button size="sm" wire:click="changeApproval('approved')" wire:confirm="Valider cette stratégie ? Toute modification ultérieure la remettra en brouillon.">Valider</x-ui.button>
                    <x-ui.button size="sm" variant="secondary" wire:click="changeApproval('rejected')">Rejeter</x-ui.button>
                    <x-ui.button size="sm" variant="ghost" wire:click="changeApproval('draft')">Remettre en brouillon</x-ui.button>
                @else
                    <x-ui.button size="sm" variant="secondary" wire:click="changeApproval('draft')">Rouvrir en brouillon</x-ui.button>
                @endif
            </span>
        </div>
        @if ($strategy->approval_note) <p class="mt-2 text-sm text-slate-700">Motif : {{ $strategy->approval_note }}</p> @endif
        <p class="mt-2 text-xs text-slate-500">Brouillon → en revue → validé ou rejeté. Modifier le contenu (contexte, constats, scénarios, phases, risques) d’une stratégie soumise ou validée la remet en brouillon : la validation porte sur un contenu précis.</p>
    </x-ui.card>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Contexte et objectifs">
            @if ($strategy->context) <x-ui.markdown :text="$strategy->context" /> @else <p class="text-sm text-amber-700">À rédiger.</p> @endif
        </x-ui.card>

        <x-ui.card title="Constats retenus">
            @if ($strategy->issues->isEmpty())
                <p class="text-sm text-slate-500">Aucun constat retenu. Choisissez dans « Modifier » les problèmes du registre auxquels la stratégie répond.</p>
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($strategy->issues as $issue)
                        <li><a href="{{ route('issues.show', $issue) }}" wire:navigate class="font-mono text-xs text-brand-700 hover:underline">{{ $issue->displayCode() }}</a> {{ $issue->title }} <x-ui.severity-badge :severity="$issue->severity" /></li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="Scénarios d’architecture" class="mt-6">
        <x-slot:actions><x-ui.button size="sm" variant="secondary" wire:click="createItem('option')">Ajouter un scénario</x-ui.button></x-slot:actions>
        @if ($strategy->options->isEmpty())
            <x-ui.empty>Aucun scénario. Décrivez au moins deux options à comparer, par exemple « Converger vers le socle » et « Réécrire le module Crédit ».</x-ui.empty>
        @else
            <div class="space-y-4">
                @foreach ($strategy->options as $option)
                    <div wire:key="option-{{ $option->id }}" @class(['rounded-md border p-4', 'border-brand-600 bg-brand-50/40' => $option->is_recommended, 'border-slate-200' => ! $option->is_recommended, 'opacity-60' => $option->isArchived()])>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs text-slate-500">{{ $option->displayCode() }}</span>
                            <span class="font-medium text-slate-900">{{ $option->name }}</span>
                            @if ($option->is_recommended) <x-ui.badge color="blue">Recommandé</x-ui.badge> @endif
                            @if ($option->effortLabel()) <span class="text-xs text-slate-600">Effort {{ $option->effortLabel() }}</span> @endif
                            @if ($option->risk_level) <span class="text-xs text-slate-600">· risque {{ mb_strtolower($option->risk_level->label()) }}</span> @endif
                            <span class="ml-auto">
                                <x-ui.button size="sm" variant="ghost" wire:click="editItem('option', {{ $option->id }})">Modifier</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" wire:click="archiveItem('option', {{ $option->id }})">{{ $option->isArchived() ? 'Restaurer' : 'Archiver' }}</x-ui.button>
                            </span>
                        </div>
                        @if ($option->description) <x-ui.markdown :text="$option->description" class="mt-2 text-sm" /> @endif
                        <div class="mt-2 grid gap-4 text-sm sm:grid-cols-2">
                            <div><p class="text-xs font-semibold uppercase text-emerald-700">Avantages</p>@if ($option->benefits)<x-ui.markdown :text="$option->benefits" />@else<p class="text-slate-400">—</p>@endif</div>
                            <div><p class="text-xs font-semibold uppercase text-red-700">Inconvénients</p>@if ($option->drawbacks)<x-ui.markdown :text="$option->drawbacks" />@else<p class="text-slate-400">—</p>@endif</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Stratégie de migration et compatibilité">
            <x-slot:actions><x-ui.button size="sm" variant="secondary" wire:click="createItem('phase')">Ajouter une phase</x-ui.button></x-slot:actions>
            @if ($strategy->migration) <x-ui.markdown :text="$strategy->migration" class="mb-4" /> @endif
            @forelse ($strategy->phases as $phase)
                <div wire:key="phase-{{ $phase->id }}" @class(['border-t border-slate-100 py-3 text-sm', 'opacity-60' => $phase->isArchived()])>
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-slate-900">Phase {{ $loop->iteration }} · {{ $phase->name }}</span>
                        <span class="ml-auto">
                            <x-ui.button size="sm" variant="ghost" wire:click="editItem('phase', {{ $phase->id }})">Modifier</x-ui.button>
                            <x-ui.button size="sm" variant="ghost" wire:click="archiveItem('phase', {{ $phase->id }})">{{ $phase->isArchived() ? 'Restaurer' : 'Archiver' }}</x-ui.button>
                        </span>
                    </div>
                    @if ($phase->description) <x-ui.markdown :text="$phase->description" class="mt-1" /> @endif
                    @if ($phase->prerequisites) <p class="mt-1 text-xs text-slate-600"><span class="font-semibold">Prérequis :</span> {{ $phase->prerequisites }}</p> @endif
                    @if ($phase->rollback) <p class="mt-1 text-xs text-slate-600"><span class="font-semibold">Retour arrière :</span> {{ $phase->rollback }}</p> @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">Aucune phase de migration.</p>
            @endforelse
        </x-ui.card>

        <x-ui.card title="Tests de non-régression et rollback">
            @if ($strategy->safety) <x-ui.markdown :text="$strategy->safety" /> @else <p class="text-sm text-amber-700">À rédiger.</p> @endif
        </x-ui.card>
    </div>

    <x-ui.card title="Risques" class="mt-6">
        <x-slot:actions><x-ui.button size="sm" variant="secondary" wire:click="createItem('risk')">Ajouter un risque</x-ui.button></x-slot:actions>
        @if ($strategy->risks->isEmpty())
            <p class="text-sm text-slate-500">Aucun risque décrit.</p>
        @else
            <x-ui.table>
                <x-slot:head><th>Risque</th><th>Probabilité</th><th>Impact</th><th>Maîtrise</th><th>Responsable</th><th></th></x-slot:head>
                @foreach ($strategy->risks as $risk)
                    <tr wire:key="risk-{{ $risk->id }}" @class(['opacity-60' => $risk->isArchived()])>
                        <td class="text-sm"><span class="font-mono text-xs text-slate-500">{{ $risk->displayCode() }}</span> {{ $risk->description }}</td>
                        <td>{{ $risk->probability?->feminineLabel() ?? '—' }}</td>
                        <td>{{ $risk->impact?->label() ?? '—' }}</td>
                        <td class="max-w-xs text-xs text-slate-600">{{ $risk->mitigation ?? '—' }}</td>
                        <td class="text-sm">{{ $risk->owner->name ?? '—' }}</td>
                        <td class="whitespace-nowrap text-right">
                            <x-ui.button size="sm" variant="ghost" wire:click="editItem('risk', {{ $risk->id }})">Modifier</x-ui.button>
                            <x-ui.button size="sm" variant="ghost" wire:click="archiveItem('risk', {{ $risk->id }})">{{ $risk->isArchived() ? 'Restaurer' : 'Archiver' }}</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.card title="Décisions" class="mt-6">
        <x-slot:actions><x-ui.button size="sm" variant="secondary" wire:click="createItem('decision')">Ajouter une décision</x-ui.button></x-slot:actions>
        @if ($strategy->decisions->isEmpty())
            <p class="text-sm text-slate-500">Aucune décision. Une décision « en attente » apparaît dans le tableau de bord DTI (L6) jusqu’à ce qu’elle soit acceptée ou rejetée.</p>
        @else
            <x-ui.table>
                <x-slot:head><th>Décision</th><th>Scénario</th><th>Statut</th><th>Justification</th><th>Décidée</th><th></th></x-slot:head>
                @foreach ($strategy->decisions as $decision)
                    <tr wire:key="decision-{{ $decision->id }}" @class(['opacity-60' => $decision->isArchived()])>
                        <td class="text-sm"><span class="font-mono text-xs text-slate-500">{{ $decision->displayCode() }}</span> {{ $decision->title }}</td>
                        <td class="text-xs">{{ $decision->option?->displayCode() ?? '—' }}</td>
                        <td><x-ui.badge :color="$decision->status->color()">{{ $decision->status->label() }}</x-ui.badge></td>
                        <td class="max-w-xs text-xs text-slate-600">{{ $decision->rationale ?? '—' }}</td>
                        <td class="text-xs text-slate-600">{{ $decision->decider ? $decision->decider->name.', '.$decision->decided_on?->format('d/m/Y') : '—' }}</td>
                        <td class="whitespace-nowrap text-right">
                            <x-ui.button size="sm" variant="ghost" wire:click="editItem('decision', {{ $decision->id }})">Modifier</x-ui.button>
                            <x-ui.button size="sm" variant="ghost" wire:click="archiveItem('decision', {{ $decision->id }})">{{ $decision->isArchived() ? 'Restaurer' : 'Archiver' }}</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.card title="Satis_SaaS — perspective future" class="mt-6">
        @if ($strategy->saas_notes) <x-ui.markdown :text="$strategy->saas_notes" /> @else <p class="text-sm text-slate-500">Sans note particulière : L7 rappelle que Satis_SaaS est une perspective future, hors du périmètre de production.</p> @endif
    </x-ui.card>

    <x-ui.audit :record="$strategy" class="mt-6" />

    <x-ui.modal wire:model="showForm" title="Modifier la stratégie">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select label="Produit" wire:model.live="form.product_id" :options="$products" required />
                <x-ui.input label="Intitulé" wire:model="form.title" required />
            </div>
            <x-ui.textarea label="Contexte et objectifs" wire:model="form.context" rows="4" hint="Markdown." />
            <x-ui.field label="Constats retenus" for="issue-ids" error="form.issue_ids" hint="Problèmes du registre auxquels la stratégie répond. Ctrl ou Cmd pour en choisir plusieurs.">
                <select id="issue-ids" multiple wire:model="form.issue_ids" size="6" class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                    @foreach ($issueOptions as $id => $label) <option value="{{ $id }}">{{ $label }}</option> @endforeach
                </select>
            </x-ui.field>
            <x-ui.textarea label="Stratégie de migration et compatibilité" wire:model="form.migration" rows="3" hint="Approche générale ; les phases se détaillent sur la fiche." />
            <x-ui.textarea label="Tests de non-régression et rollback" wire:model="form.safety" rows="3" />
            <x-ui.textarea label="Satis_SaaS" wire:model="form.saas_notes" rows="2" hint="Facultatif : ce que la stratégie prépare pour la perspective SaaS, sans l’inclure dans le périmètre." />
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showItemForm" :title="match ($itemForm->kind) { 'phase' => 'Phase de migration', 'risk' => 'Risque', 'decision' => 'Décision', default => 'Scénario' }">
        <form wire:submit="saveItem" class="space-y-4">
            @if ($itemForm->kind === 'option')
                <x-ui.input label="Nom" wire:model="itemForm.name" required />
                <x-ui.textarea label="Description" wire:model="itemForm.description" rows="3" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.textarea label="Avantages" wire:model="itemForm.benefits" rows="3" />
                    <x-ui.textarea label="Inconvénients" wire:model="itemForm.drawbacks" rows="3" />
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.input label="Effort min (j/h)" wire:model="itemForm.effort_min_days" inputmode="decimal" />
                    <x-ui.input label="Effort max (j/h)" wire:model="itemForm.effort_max_days" inputmode="decimal" />
                    <x-ui.select label="Niveau de risque" wire:model="itemForm.risk_level" :options="$levels" placeholder="—" />
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="itemForm.is_recommended" class="rounded border-slate-300 text-brand-700"> Scénario recommandé</label>
            @elseif ($itemForm->kind === 'phase')
                <x-ui.input label="Nom" wire:model="itemForm.name" required hint="Ex. Phase 1 — Isoler le module Crédit derrière une API" />
                <x-ui.textarea label="Description" wire:model="itemForm.description" rows="3" />
                <x-ui.textarea label="Prérequis" wire:model="itemForm.prerequisites" rows="2" />
                <x-ui.textarea label="Retour arrière" wire:model="itemForm.rollback" rows="2" />
            @elseif ($itemForm->kind === 'risk')
                <x-ui.textarea label="Risque" wire:model="itemForm.description" rows="2" required />
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.select label="Probabilité" wire:model="itemForm.probability" :options="$levels" placeholder="—" />
                    <x-ui.select label="Impact" wire:model="itemForm.impact" :options="$levels" placeholder="—" />
                    <x-ui.select label="Responsable" wire:model="itemForm.owner_id" :options="$users" placeholder="—" />
                </div>
                <x-ui.textarea label="Mesure de maîtrise" wire:model="itemForm.mitigation" rows="2" />
            @else
                <x-ui.input label="Décision" wire:model="itemForm.name" required hint="Ex. Retenir la convergence vers le socle pour Satis Macro" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.select label="Scénario" wire:model="itemForm.strategy_option_id" :options="$optionOptions" placeholder="—" />
                    <x-ui.select label="Statut" wire:model.live="itemForm.status" :options="$decisionStatuses" required />
                    <x-ui.input label="Date de décision" type="date" wire:model="itemForm.decided_on" hint="Vide : aujourd’hui." />
                </div>
                <x-ui.textarea label="Justification" wire:model="itemForm.rationale" rows="3" :required="$itemForm->status !== 'proposed'" />
            @endif
            @error('itemForm.name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showReject" title="Rejeter la stratégie">
        <form wire:submit="changeApproval('rejected')" class="space-y-4">
            <x-ui.textarea label="Motif" wire:model="approvalNote" rows="3" required />
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Rejeter</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
