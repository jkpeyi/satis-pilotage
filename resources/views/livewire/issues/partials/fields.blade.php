{{-- Issue fields, bound to an IssueForm named $prefix. Uses $issueTypes / $types, $severities, $issueStatuses / $statuses. --}}
@php($typeOptions = $issueTypes ?? $types)
@php($statusOptions = $issueStatuses ?? $statuses)
<x-ui.input label="Titre" wire:model="{{ $prefix }}.title" required />
<x-ui.textarea label="Description" wire:model="{{ $prefix }}.description" rows="5" required hint="Constat, impact, comment le reproduire. Markdown." />
<div class="grid gap-4 sm:grid-cols-3">
    <x-ui.select label="Type" wire:model="{{ $prefix }}.type" :options="$typeOptions" required />
    <x-ui.select label="Gravité" wire:model="{{ $prefix }}.severity" :options="$severities" required />
    <x-ui.select label="Statut" wire:model="{{ $prefix }}.status" :options="$statusOptions" required />
</div>
<x-ui.textarea label="Solution proposée" wire:model="{{ $prefix }}.proposed_solution" rows="4" hint="Markdown. Le code de la solution s'ajoute en extrait." />
<x-ui.input label="Effort estimé (jours/homme)" wire:model="{{ $prefix }}.effort_days" inputmode="decimal" class="w-40" />
