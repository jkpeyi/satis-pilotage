{{-- Issue fields, bound to an IssueForm named $prefix. Uses $issueTypes / $types, $severities, $issueStatuses / $statuses, and IssueFieldOptions. --}}
@php
    $typeOptions = $issueTypes ?? $types;
    $statusOptions = $issueStatuses ?? $statuses;
@endphp
<x-ui.input label="Titre" wire:model="{{ $prefix }}.title" required />
<x-ui.textarea label="Description" wire:model="{{ $prefix }}.description" rows="5" required hint="Constat, comment le reproduire. Markdown." />
<x-ui.textarea label="Impact" wire:model="{{ $prefix }}.impact" rows="2" hint="Conséquence pour le métier ou l'exploitation (ex. pénalités mal calculées pour tous les crédits)." />
<div class="grid gap-4 sm:grid-cols-3">
    <x-ui.select label="Type" wire:model="{{ $prefix }}.type" :options="$typeOptions" required />
    <x-ui.select label="Gravité" wire:model="{{ $prefix }}.severity" :options="$severities" required />
    <x-ui.select label="Statut" wire:model="{{ $prefix }}.status" :options="$statusOptions" required />
</div>
<x-ui.textarea label="Solution proposée" wire:model="{{ $prefix }}.proposed_solution" rows="4" hint="Markdown. Le code de la solution s'ajoute en extrait." />
<div class="grid gap-4 sm:grid-cols-3">
    <x-ui.input label="Effort minimum (j/h)" wire:model="{{ $prefix }}.effort_min_days" inputmode="decimal" />
    <x-ui.input label="Effort maximum (j/h)" wire:model="{{ $prefix }}.effort_max_days" inputmode="decimal" hint="Vide : égal au minimum." />
    <x-ui.select label="Confiance" wire:model="{{ $prefix }}.confidence" :options="$confidences" placeholder="—" />
</div>
<div class="grid gap-4 sm:grid-cols-3">
    <x-ui.select label="Responsable" wire:model="{{ $prefix }}.owner_id" :options="$users" placeholder="À désigner" />
    <x-ui.select label="Dépôt" wire:model="{{ $prefix }}.repository_id" :options="$issueRepositories" placeholder="—" />
    <x-ui.select label="Composant" wire:model="{{ $prefix }}.component_id" :options="$issueComponents" placeholder="—" />
</div>
<x-ui.input label="Ticket GitLab" type="url" wire:model="{{ $prefix }}.gitlab_issue_url" placeholder="https://gitlab…/-/issues/123" />
