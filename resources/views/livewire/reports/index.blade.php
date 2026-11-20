<div>
    <x-ui.page-header title="Rapports" description="Livrables du plan DTI générés depuis les données de l’application, sans double saisie. Chaque export est figé et conservé." />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Générer un livrable" class="lg:col-span-1">
            <form wire:submit="generate" class="space-y-4">
                <x-ui.select label="Livrable" wire:model.live="form.code" required>
                    @foreach ($deliverables as $code => $deliverable)
                        <option value="{{ $code }}" @disabled(! in_array($code, $exportable, true))>
                            {{ $code }} — {{ $deliverable['title'] }}{{ in_array($code, $exportable, true) ? '' : ' (à venir)' }}
                        </option>
                    @endforeach
                </x-ui.select>
                <x-ui.select label="Produit" wire:model.live="form.product_id" :options="$products" placeholder="Choisir un produit" required />
                @if ($form->code === 'L4')
                    <x-ui.select label="Campagne de diagnostic" wire:model="form.campaign_id" :options="$campaigns" placeholder="Choisir une campagne" required hint="L4 porte sur un dépôt analysé à un commit précis." />
                @else
                    <x-ui.select label="Client" wire:model="form.customer_id" :options="$customers" placeholder="Tous les clients" hint="Restreint le rapport aux versions de ce client." />
                @endif

                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="generate">
                    <span wire:loading.remove wire:target="generate">Générer DOCX et XLSX</span>
                    <span wire:loading wire:target="generate">Génération…</span>
                </x-ui.button>

                <p class="text-xs text-slate-500">
                    Le rapport est un brouillon : le circuit de validation n’est pas encore activé. Les données absentes de l’application apparaissent « À confirmer » et sont listées dans le bloc « Validation et réserves ».
                </p>
            </form>
        </x-ui.card>

        <div class="space-y-3 lg:col-span-2">
            <div class="flex items-center justify-between gap-3">
                <h2 class="whitespace-nowrap font-semibold text-slate-900">Registre des exports</h2>
                <x-ui.archived-toggle wire:model.live="showDeleted" class="ml-auto">Afficher les exports supprimés</x-ui.archived-toggle>
                <select wire:model.live="filterCode" aria-label="Livrable" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                    <option value="">Tous les livrables</option>
                    @foreach ($deliverables as $code => $deliverable) <option value="{{ $code }}">{{ $code }} — {{ $deliverable['title'] }}</option> @endforeach
                </select>
            </div>

            @if ($generations->isEmpty())
                <x-ui.empty>Aucun rapport généré pour l’instant.</x-ui.empty>
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th>Document</th>
                        <th>Périmètre</th>
                        <th>Complétude</th>
                        <th>Généré</th>
                        <th class="text-right">Fichiers</th>
                    </x-slot:head>
                    @foreach ($generations as $generation)
                        <tr wire:key="generation-{{ $generation->id }}" @class(['opacity-60' => $generation->isDeleted()])>
                            <td>
                                <div class="font-mono text-xs text-slate-500">{{ $generation->documentId() }} · v{{ $generation->version_number }}</div>
                                <div class="font-medium text-slate-900">{{ $generation->title }}</div>
                                <x-ui.badge>{{ $generation->statusLabel() }}</x-ui.badge>
                            </td>
                            <td class="text-slate-700">{{ $generation->scope_label }}</td>
                            <td>
                                <x-ui.badge :color="$generation->missing_count === 0 ? 'green' : 'amber'">{{ number_format((float) $generation->completeness, 0, ',', ' ') }} %</x-ui.badge>
                                @if ($generation->missing_count > 0)
                                    <div class="mt-1 text-xs text-slate-500">{{ $generation->missing_count }} à confirmer</div>
                                @endif
                            </td>
                            <td class="text-xs text-slate-600">
                                {{ $generation->created_at?->format('d/m/Y H:i') }} UTC<br>
                                {{ $generation->author?->name }}
                                <div class="font-mono text-[10px] text-slate-400" title="Empreinte des données (SHA-256) : {{ $generation->data_sha256 }}">{{ substr($generation->data_sha256, 0, 12) }}</div>
                            </td>
                            <td class="whitespace-nowrap text-right">
                                @if ($generation->isDeleted())
                                    <span class="text-xs text-slate-500">
                                        Supprimé le {{ $generation->deleted_at?->format('d/m/Y') }}@if ($generation->deleter) par {{ $generation->deleter->name }}@endif
                                    </span>
                                @else
                                    @if ($generation->docx_path)
                                        <a href="{{ route('reports.download', [$generation, 'docx']) }}" class="font-medium text-brand-700 hover:underline">DOCX</a>
                                    @endif
                                    @if ($generation->xlsx_path)
                                        <a href="{{ route('reports.download', [$generation, 'xlsx']) }}" class="ml-3 font-medium text-brand-700 hover:underline">XLSX</a>
                                    @endif
                                    <button type="button" wire:click="delete({{ $generation->id }})"
                                        wire:confirm="Supprimer l'export {{ $generation->documentId() }} ? Ses fichiers DOCX et XLSX seront effacés ; une trace reste dans le registre."
                                        class="ml-3 text-xs text-slate-500 hover:text-red-600">Supprimer</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
                <div>{{ $generations->links() }}</div>
            @endif
        </div>
    </div>
</div>
