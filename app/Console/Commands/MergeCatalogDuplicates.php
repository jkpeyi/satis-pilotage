<?php

namespace App\Console\Commands;

use App\Actions\Catalog\MergeCatalogItems;
use App\Models\Feature;
use App\Models\Module;
use App\Queries\CatalogDuplicates;
use Illuminate\Console\Command;

/**
 * Removes the catalog duplicates: same-kind items sharing a name are merged into one (everything
 * they hold moves to it) and deleted. Without --force, only shows what would be done.
 */
class MergeCatalogDuplicates extends Command
{
    protected $signature = 'catalog:merge-duplicates {--force : Fusionner réellement (sinon simple aperçu)}';

    protected $description = 'Fusionner et supprimer les doublons du catalogue (modules, sous-modules, fonctionnalités)';

    public function handle(CatalogDuplicates $duplicates, MergeCatalogItems $merge): int
    {
        $groups = $duplicates->mergeableGroups();
        $crossKind = $duplicates->crossKindGroups();
        $force = (bool) $this->option('force');

        if ($groups === [] && $crossKind === []) {
            $this->info('Aucun doublon dans le catalogue.');

            return self::SUCCESS;
        }

        foreach ($groups as $group) {
            $kept = array_shift($group['items']);
            $this->line(sprintf(
                '<info>%s</info> « %s » : on garde %s %s (%d rattachement(s))',
                $group['kind'] === 'module' ? 'Module' : 'Fonctionnalité',
                $kept['item']->name,
                $kept['item']->displayCode(),
                $kept['path'],
                $kept['implementations'],
            ));

            foreach ($group['items'] as $duplicate) {
                $this->line(sprintf('    fusionne et supprime %s %s (%d rattachement(s))', $duplicate['item']->displayCode(), $duplicate['path'], $duplicate['implementations']));

                if ($force) {
                    /** @var Module|Feature $keptItem */
                    $keptItem = $kept['item']->fresh();
                    /** @var Module|Feature|null $duplicateItem */
                    $duplicateItem = $duplicate['item']->fresh();
                    if ($duplicateItem !== null) {
                        $summary = $merge->handle($duplicateItem, $keptItem);
                        $this->line(sprintf(
                            '      → %d rattachement(s) déplacé(s), %d fusionné(s), %d sous-élément(s), %d règle(s), %d problème(s), %d étape(s) de workflow',
                            $summary['implementations_moved'], $summary['implementations_merged'], $summary['children_moved'],
                            $summary['rules_moved'], $summary['issues_moved'], $summary['steps_moved'],
                        ));
                    }
                }
            }
        }

        foreach ($crossKind as $group) {
            $this->warn(sprintf(
                'À traiter à la main : « %s » est à la fois un module et une fonctionnalité (%s). Convertissez l’un des deux, puis fusionnez depuis l’écran « Doublons ».',
                $group['items'][0]['item']->name,
                implode(' ; ', array_map(fn (array $entry): string => $entry['item']->displayCode().' '.$entry['path'], $group['items'])),
            ));
        }

        if (! $force && $groups !== []) {
            $this->newLine();
            $this->comment('Aperçu seulement. Relancez avec --force pour fusionner. Chaque fusion est tracée dans la table catalog_merges.');
        }

        return self::SUCCESS;
    }
}
