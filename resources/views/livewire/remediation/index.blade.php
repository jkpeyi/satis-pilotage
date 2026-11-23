<div>
    <x-ui.page-header title="Plan d’actions" description="Feuille de route de remédiation (L8) : lots avec leur jalon, et actions qui traitent les problèmes du registre de dette.">
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="createPackage">Nouveau lot</x-ui.button>
            <x-ui.button wire:click="create">Nouvelle action</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @php
        $days = fn ($value): string => rtrim(rtrim(number_format((float) $value, 1, ',', ' '), '0'), ',').' j/h';
    @endphp

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <select wire:model.live="product" aria-label="Produit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            @foreach ($products as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
        </select>
    </div>

    <x-ui.card title="Lots et jalons" class="mb-6">
        @if ($packages->isEmpty())
            <x-ui.empty>Aucun lot. Regroupez les actions en lots livrés à une date (jalon), par exemple « Lot 1 — Sécurité ».</x-ui.empty>
        @else
            <x-ui.table>
                <x-slot:head>
                    <th>Lot</th>
                    <th>Début</th>
                    <th>Jalon</th>
                    <th>Statut</th>
                    <th class="text-right">Actions terminées</th>
                    <th class="text-right">Effort</th>
                    <th></th>
                </x-slot:head>
                @foreach ($packages as $workPackage)
                    <tr wire:key="package-{{ $workPackage->id }}">
                        <td>
                            <button type="button" wire:click="$set('package', '{{ $workPackage->id }}')" class="text-left font-medium text-slate-900 hover:text-brand-700">
                                <span class="font-mono text-xs text-slate-500">{{ $workPackage->displayCode() }}</span> {{ $workPackage->name }}
                            </button>
                            @if ($workPackage->objective) <div class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($workPackage->objective, 140) }}</div> @endif
                        </td>
                        <td class="whitespace-nowrap">{{ $workPackage->starts_on?->format('d/m/Y') ?? '—' }}</td>
                        <td @class(['whitespace-nowrap', 'font-medium text-red-700' => $workPackage->due_on?->isPast() && ! in_array($workPackage->status->value, ['done', 'cancelled'], true)])>{{ $workPackage->due_on?->format('d/m/Y') ?? '—' }}</td>
                        <td><x-ui.badge>{{ $workPackage->status->label() }}</x-ui.badge></td>
                        <td class="text-right tabular-nums">{{ $workPackage->actions_done }} / {{ $workPackage->actions_total }}</td>
                        <td class="text-right tabular-nums">{{ $workPackage->effort !== null ? $days($workPackage->effort) : '—' }}</td>
                        <td class="text-right"><x-ui.button size="sm" variant="ghost" wire:click="editPackage({{ $workPackage->id }})">Modifier</x-ui.button></td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <select wire:model.live="status" aria-label="Statut" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="open">À faire, en cours ou bloquées</option>
            <option value="">Tous les statuts</option>
            @foreach ($statuses as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
        </select>
        <select wire:model.live="package" aria-label="Lot" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les lots</option>
            <option value="none">Hors lot</option>
            @foreach ($packages as $workPackage) <option value="{{ $workPackage->id }}">{{ $workPackage->displayCode() }} · {{ $workPackage->name }}</option> @endforeach
        </select>
        <select wire:model.live="owner" aria-label="Responsable" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les responsables</option>
            <option value="none">Sans responsable</option>
            @foreach ($users as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
        </select>
        <label class="flex items-center gap-2 py-2 text-sm text-slate-700"><input type="checkbox" wire:model.live="lateOnly" class="rounded border-slate-300 text-brand-700"> En retard seulement</label>
    </div>

    @if ($actions->isEmpty())
        <x-ui.empty>Aucune action avec ces filtres. Planifiez une action depuis la fiche d’un problème, ou avec « Nouvelle action ».</x-ui.empty>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>Action</th>
                <th>Problème</th>
                <th>Priorité</th>
                <th>Responsable</th>
                <th>Échéance</th>
                <th class="text-right">Effort</th>
                <th>Dépend de</th>
                <th>Statut</th>
                <th></th>
            </x-slot:head>
            @foreach ($actions as $action)
                <tr wire:key="action-{{ $action->id }}">
                    <td class="max-w-sm">
                        <div class="font-medium text-slate-900"><span class="font-mono text-xs text-slate-500">{{ $action->displayCode() }}</span> {{ $action->title }}</div>
                        @if ($action->workPackage) <div class="text-xs text-slate-500">{{ $action->workPackage->displayCode() }} · {{ $action->workPackage->name }}</div> @endif
                    </td>
                    <td class="text-xs">
                        @if ($action->issue)
                            <a href="{{ route('issues.show', $action->issue) }}" wire:navigate class="font-mono text-brand-700 hover:underline">{{ $action->issue->displayCode() }}</a>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td><x-ui.badge :color="$action->priority->color()">{{ $action->priority->shortLabel() }}</x-ui.badge></td>
                    <td class="text-sm">{!! $action->owner ? e($action->owner->name) : '<span class="text-amber-700">À désigner</span>' !!}</td>
                    <td @class(['whitespace-nowrap text-sm', 'font-medium text-red-700' => $action->isLate()])>
                        {{ $action->due_on?->format('d/m/Y') ?? '—' }}
                        @if ($action->isLate()) <div class="text-xs">en retard</div> @endif
                    </td>
                    <td class="text-right tabular-nums">{{ $action->effort_days !== null ? $days($action->effort_days) : '—' }}</td>
                    <td class="text-xs">
                        @foreach ($action->dependencies as $dependency)
                            <span @class(['font-mono', 'text-slate-400 line-through' => $dependency->status->value === 'done', 'text-slate-700' => $dependency->status->value !== 'done']) title="{{ $dependency->title }} — {{ $dependency->status->label() }}">{{ $dependency->displayCode() }}</span>
                        @endforeach
                    </td>
                    <td><x-ui.badge :color="$action->status->color()">{{ $action->status->label() }}</x-ui.badge></td>
                    <td class="whitespace-nowrap text-right">
                        <x-ui.button size="sm" variant="ghost" wire:click="edit({{ $action->id }})">Modifier</x-ui.button>
                        <x-ui.button size="sm" variant="ghost" wire:click="toggleArchive({{ $action->id }})" wire:confirm="Archiver cette action ?">Archiver</x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    <x-ui.modal wire:model="showForm" :title="$form->action ? 'Modifier '.$form->action->displayCode() : 'Nouvelle action'">
        <form wire:submit="save" class="space-y-4">
            <x-ui.input label="Intitulé" wire:model="form.title" required />
            <x-ui.select label="Problème traité" wire:model="form.issue_id" :options="$issueOptions" placeholder="Aucun (action transverse)" hint="Problèmes du registre de dette concernant ce produit." />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.select label="Lot" wire:model="form.work_package_id" :options="$packageOptions" placeholder="Hors lot" />
                <x-ui.select label="Priorité" wire:model="form.priority" :options="$priorities" required />
                <x-ui.select label="Statut" wire:model.live="form.status" :options="$statuses" required />
            </div>
            <div class="grid gap-4 sm:grid-cols-4">
                <x-ui.select label="Responsable" wire:model="form.owner_id" :options="$users" placeholder="À désigner" />
                <x-ui.input label="Début" type="date" wire:model="form.starts_on" />
                <x-ui.input label="Échéance" type="date" wire:model="form.due_on" />
                <x-ui.input label="Effort (j/h)" wire:model="form.effort_days" inputmode="decimal" />
            </div>
            <x-ui.textarea label="Description" wire:model="form.description" rows="2" hint="Markdown." />
            <x-ui.textarea label="Critères de réception" wire:model="form.acceptance_criteria" rows="3" :required="$form->status === 'done'"
                hint="Ce qui doit être vrai pour considérer l’action terminée, ex. « plus aucune requête concaténée dans LoanRepository ; tests d’injection verts »." />
            <x-ui.textarea label="Preuve de test" wire:model="form.test_evidence" rows="2" :required="$form->status === 'done'"
                hint="Lien vers le pipeline, le rapport de tests ou le PV de recette qui vérifie les critères. Obligatoire pour terminer l’action." />
            <x-ui.field label="Dépend de" for="dependency-ids" error="form.dependency_ids" hint="Actions à terminer avant celle-ci. Ctrl ou Cmd pour en choisir plusieurs.">
                <select id="dependency-ids" multiple wire:model="form.dependency_ids" size="4" class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                    @foreach ($dependencyOptions as $id => $label) <option value="{{ $id }}">{{ $label }}</option> @endforeach
                </select>
            </x-ui.field>
            <x-ui.input label="Ticket GitLab" type="url" wire:model="form.gitlab_issue_url" />
            @error('form.save') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire:model="showPackageForm" :title="$packageForm->workPackage ? 'Modifier le lot' : 'Nouveau lot'">
        <form wire:submit="savePackage" class="space-y-4">
            <x-ui.input label="Nom" wire:model="packageForm.name" required hint="Ex. Lot 1 — Sécurité des accès aux données" />
            <x-ui.textarea label="Objectif" wire:model="packageForm.objective" rows="2" />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input label="Début" type="date" wire:model="packageForm.starts_on" />
                <x-ui.input label="Jalon" type="date" wire:model="packageForm.due_on" hint="Date de livraison du lot." />
                <x-ui.select label="Statut" wire:model="packageForm.status" :options="$packageStatuses" required />
            </div>
            <div class="flex gap-2 pt-2">
                <x-ui.button type="submit">Enregistrer</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="open = false">Annuler</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
