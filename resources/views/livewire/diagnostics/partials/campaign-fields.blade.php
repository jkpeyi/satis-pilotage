<x-ui.select label="Dépôt" wire:model="form.repository_id" :options="$repositories" placeholder="Choisir…" required />
<div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
    <x-ui.input label="Commit analysé" wire:model="form.commit_sha" required class="font-mono" hint="SHA complet de préférence : les liens de preuve pointent sur ce commit." />
    <x-ui.input label="Date d’analyse" type="date" wire:model="form.analyzed_on" />
</div>
<x-ui.input label="Intitulé" wire:model="form.title" required hint="Ex. Audit initial avant refactoring" />
<x-ui.textarea label="Objet" wire:model="form.objective" rows="3" hint="Ce que la campagne cherche à établir. Markdown." />
<x-ui.textarea label="Limites" wire:model="form.limitations" rows="3" hint="Ce qui n’a pas été couvert : dossiers exclus, outils non lancés, pas d’accès à la base… Repris dans L4." />
