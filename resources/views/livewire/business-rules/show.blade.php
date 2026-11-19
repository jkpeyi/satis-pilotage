<div>
    <x-ui.page-header :title="$rule->title">
        <x-slot:breadcrumbs>
            <a href="{{ route('business-rules.index') }}" wire:navigate class="hover:text-slate-900">Règles métier</a>
            / <span class="font-mono">{{ $rule->displayCode() }}</span>
            @if ($rule->isArchived()) <x-ui.badge>Archivée</x-ui.badge> @endif
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('implementations.show', $rule->implementation)">Fiche de l'élément</x-ui.button>
            <x-ui.button variant="ghost" wire:click="toggleArchive" wire:confirm="{{ $rule->isArchived() ? 'Restaurer cette règle ?' : 'Archiver cette règle ?' }}">
                {{ $rule->isArchived() ? 'Restaurer' : 'Archiver' }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <p class="mb-6 text-sm text-slate-600">
        {{ $rule->implementation->version->product->name }} · {{ $rule->implementation->version->name }}
        — {{ $rule->implementation->catalogItem()->typeLabel() }} <strong>{{ $rule->implementation->catalogItem()->name }}</strong>
    </p>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            <x-ui.card>
                <x-ui.markdown :text="$rule->description" class="mb-6" />
                <details class="border-t border-slate-200 pt-4">
                    <summary class="cursor-pointer text-sm font-medium text-slate-700">Modifier la règle</summary>
                    <form wire:submit="save" class="mt-4 space-y-4">
                        <x-ui.input label="Intitulé" wire:model="form.title" required />
                        <x-ui.textarea label="Description" wire:model="form.description" rows="6" required hint="Markdown accepté." />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.select label="Source" wire:model="form.source" :options="$sources" required />
                            <x-ui.select label="Statut" wire:model="form.status" :options="$statuses" required />
                        </div>
                        <x-ui.button type="submit">Enregistrer</x-ui.button>
                    </form>
                </details>
            </x-ui.card>

            @include('livewire.partials.snippets', ['withSolution' => false])
        </div>

        <aside class="min-w-0 space-y-6">
            <x-ui.card>
                <div class="flex flex-wrap gap-2">
                    <x-ui.badge>{{ $rule->source->label() }}</x-ui.badge>
                    <x-ui.badge :color="$rule->status === \App\Enums\RuleStatus::Validated ? 'green' : 'amber'">{{ $rule->status->label() }}</x-ui.badge>
                </div>
                <x-ui.audit :record="$rule" class="mt-4" />
            </x-ui.card>

            <x-ui.card title="Étapes de workflow">
                @if ($rule->workflowSteps->isEmpty())
                    <p class="text-sm text-slate-500">Aucune étape liée. Le lien se fait depuis la fiche d'un workflow.</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($rule->workflowSteps as $step)
                            <li wire:key="rule-step-{{ $step->id }}">
                                <a href="{{ route('workflows.show', $step->workflow) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $step->workflow->name }}</a>
                                <span class="text-slate-500">· étape {{ $step->position }} : {{ $step->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card title="Même règle dans d'autres versions">
                @if ($family->isEmpty())
                    <p class="text-sm text-slate-500">Cette règle n'existe que dans cette version.</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($family as $relative)
                            <li wire:key="relative-{{ $relative->id }}">
                                <a href="{{ route('business-rules.show', $relative) }}" wire:navigate class="hover:text-brand-700">
                                    <span class="font-mono text-xs text-slate-500">{{ $relative->displayCode() }}</span>
                                    {{ $relative->implementation->version->name }}
                                </a>
                                @if ($relative->id === ($rule->origin_rule_id ?? 0)) <x-ui.badge color="blue">Origine</x-ui.badge> @endif
                                @if ($relative->title !== $rule->title || $relative->description !== $rule->description)
                                    <x-ui.badge color="amber">Différente</x-ui.badge>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($copyCandidates->isNotEmpty() && ! $rule->isArchived())
                    <form wire:submit="copy" class="mt-4 space-y-3 border-t border-slate-200 pt-4">
                        <x-ui.field label="Copier vers d'autres versions" error="copyVersionIds" hint="Ctrl ou Cmd pour en choisir plusieurs. Les extraits de code sont copiés aussi.">
                            <select multiple size="5" wire:model="copyVersionIds" class="block w-full rounded-md border border-slate-300 bg-white px-2 py-1 text-sm">
                                @foreach ($copyCandidates as $version)
                                    <option value="{{ $version->id }}">{{ $version->product->name }} · {{ $version->name }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model="copyAttachMissing" class="rounded border-slate-300 text-brand-700">
                            Rattacher l'élément aux versions qui ne l'ont pas
                        </label>
                        <x-ui.button type="submit" size="sm">Copier</x-ui.button>
                    </form>
                @endif
            </x-ui.card>
        </aside>
    </div>
</div>
