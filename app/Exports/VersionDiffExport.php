<?php

namespace App\Exports;

use App\Models\Implementation;
use App\Models\Version;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Excel export of the differences between two versions: one sheet per section.
 */
final class VersionDiffExport implements Export, WithMultipleSheets
{
    /**
     * @param  array{onlyInA: list<array<string, mixed>>, onlyInB: list<array<string, mixed>>, different: list<array<string, mixed>>, counts: array<string, int>}  $diff
     */
    public function __construct(private readonly array $diff, private readonly Version $a, private readonly Version $b) {}

    /**
     * @return list<SimpleSheet>
     */
    public function sheets(): array
    {
        $onlyIn = function (array $entries, Version $other): array {
            $rows = [];
            foreach ($entries as $entry) {
                /** @var Implementation|null $otherImplementation */
                $otherImplementation = $entry['other'];
                $rows[] = [
                    $entry['name'],
                    $entry['typeLabel'],
                    $entry['path'],
                    $entry['implementation']->status->label(),
                    $otherImplementation !== null ? 'Marqué « '.$otherImplementation->status->label().' »' : 'Non rattaché',
                    implode(', ', $entry['children']),
                ];
            }

            return $rows;
        };

        $different = [];
        foreach ($this->diff['different'] as $entry) {
            $different[] = [
                $entry['name'], $entry['typeLabel'], $entry['path'], implode(', ', $entry['reasons']),
                $entry['a']->status->label(), $entry['b']->status->label(),
                (string) $entry['a']->deviation, (string) $entry['b']->deviation,
            ];
        }

        return [
            new SimpleSheet('Uniquement '.$this->short($this->a), ['Élément', 'Type', 'Chemin', 'État dans '.$this->a->name, 'Dans '.$this->b->name, 'Sous-éléments inclus'], $onlyIn($this->diff['onlyInA'], $this->b)),
            new SimpleSheet('Uniquement '.$this->short($this->b), ['Élément', 'Type', 'Chemin', 'État dans '.$this->b->name, 'Dans '.$this->a->name, 'Sous-éléments inclus'], $onlyIn($this->diff['onlyInB'], $this->a)),
            new SimpleSheet('Présents mais différents', ['Élément', 'Type', 'Chemin', 'Différences', 'État '.$this->a->name, 'État '.$this->b->name, 'Écart '.$this->a->name, 'Écart '.$this->b->name], $different),
        ];
    }

    /**
     * Sheet titles are limited to 31 characters and some symbols.
     */
    private function short(Version $version): string
    {
        return mb_substr((string) preg_replace('/[\\\\\/?*\[\]:]/', ' ', $version->name), 0, 19);
    }
}
