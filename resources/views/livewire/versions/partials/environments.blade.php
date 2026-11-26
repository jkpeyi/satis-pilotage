<x-ui.card title="Environnements">
    <x-slot:actions>
        <x-ui.button size="sm" wire:click="createEnvironment">Ajouter un environnement</x-ui.button>
    </x-slot:actions>
    @if ($environments->isEmpty())
        <x-ui.empty>Aucun environnement. Ajoutez par exemple la production, la pré-production et le test de cette application.</x-ui.empty>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>Environnement</th>
                <th>Type</th>
                <th>Remarques</th>
                <th></th>
            </x-slot:head>
            @foreach ($environments as $environment)
                <tr wire:key="environment-{{ $environment->id }}" @class(['opacity-60' => $environment->isArchived()])>
                    <td>
                        <span class="font-mono text-xs text-slate-500">{{ $environment->displayCode() }}</span>
                        <span class="font-medium text-slate-900">{{ $environment->name }}</span>
                        @if ($environment->isArchived()) <x-ui.badge>Archivé</x-ui.badge> @endif
                    </td>
                    <td><x-ui.badge :color="$environment->type === \App\Enums\EnvironmentType::Production ? 'blue' : 'gray'">{{ $environment->type->label() }}</x-ui.badge></td>
                    <td class="max-w-md text-sm text-slate-600">
                        @if ($environment->notes) <x-ui.markdown :text="$environment->notes" /> @else — @endif
                    </td>
                    <td class="whitespace-nowrap text-right">
                        @unless ($environment->isArchived())
                            <x-ui.button size="sm" variant="ghost" wire:click="editEnvironment({{ $environment->id }})">Modifier</x-ui.button>
                        @endunless
                        <x-ui.button size="sm" variant="ghost" wire:click="toggleEnvironmentArchive({{ $environment->id }})">{{ $environment->isArchived() ? 'Restaurer' : 'Archiver' }}</x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-ui.card>
