<?php

namespace App\Exports;

use App\Enums\ImplementationStatus;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Excel export of the comparison matrix as shown on screen (EF-307):
 * same rows (after filters and collapse), same pivot, status colors, differing cells in bold.
 */
final class ComparisonMatrixExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    private const FILLS = [
        'not_assessed' => 'EDEDED',
        'operational' => 'C6EFCE',
        'needs_improvement' => 'FFEB9C',
        'defective' => 'FFC7CE',
        'missing' => 'E4DFEC',
    ];

    /**
     * @param  array{versions: list<array{id: int, name: string, customer: ?string, reference: bool}>, rows: list<array{key: string, type: string, id: int, name: string, code: ?string, depth: int, parent: ?string}>, cells: array<string, array<int, array{id: int, s: string, d: bool, r: int, i: int}>>}  $data
     * @param  list<string>  $rowKeys  rows visible on screen, in order
     */
    public function __construct(private readonly array $data, private readonly array $rowKeys, private readonly ?int $pivotId) {}

    public function title(): string
    {
        return 'Cartographie';
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        $header = ['Élément', 'Type', 'Code'];
        foreach ($this->data['versions'] as $version) {
            $header[] = $version['name'].($version['id'] === $this->pivotId ? ' (pivot)' : '');
        }

        $lines = [$header];
        foreach ($this->rows() as $row) {
            $line = [
                str_repeat('    ', $row['depth']).$row['name'],
                $row['type'] === 'feature' ? 'Fonctionnalité' : ($row['depth'] > 0 ? 'Sous-module' : 'Module'),
                (string) $row['code'],
            ];

            foreach ($this->data['versions'] as $version) {
                $line[] = $this->cellText($this->data['cells'][$row['key']][$version['id']] ?? null);
            }

            $lines[] = $line;
        }

        $lines[] = [];
        $lines[] = ['Légende : « — » non rattaché (hors périmètre du client) ; « ≠ » description fonctionnelle renseignée ; RM règles métier ; PB problèmes. En gras : différent de la version pivot.'];

        return $lines;
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = Coordinate::stringFromColumnIndex(3 + count($this->data['versions']));

                $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
                $sheet->freezePane('B2');

                foreach ($this->rows() as $index => $row) {
                    $line = $index + 2;

                    if ($row['type'] === 'module') {
                        $sheet->getStyle("A{$line}")->getFont()->setBold(true);
                    }

                    $pivotCell = $this->pivotId !== null ? ($this->data['cells'][$row['key']][$this->pivotId] ?? null) : null;

                    foreach ($this->data['versions'] as $position => $version) {
                        $cell = $this->data['cells'][$row['key']][$version['id']] ?? null;
                        $coordinate = Coordinate::stringFromColumnIndex(4 + $position).$line;
                        $style = $sheet->getStyle($coordinate);

                        if ($cell !== null) {
                            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::FILLS[$cell['s']] ?? 'FFFFFF');
                        }

                        if ($this->pivotId !== null && $version['id'] !== $this->pivotId && $this->signature($cell) !== $this->signature($pivotCell)) {
                            $style->getFont()->setBold(true);
                        }
                    }
                }
            },
        ];
    }

    /**
     * @return list<array{key: string, type: string, id: int, name: string, code: ?string, depth: int, parent: ?string}>
     */
    private function rows(): array
    {
        $byKey = [];
        foreach ($this->data['rows'] as $row) {
            $byKey[$row['key']] = $row;
        }

        return array_values(array_filter(array_map(fn (string $key): ?array => $byKey[$key] ?? null, $this->rowKeys)));
    }

    /**
     * @param  array{id: int, s: string, d: bool, r: int, i: int}|null  $cell
     */
    private function cellText(?array $cell): string
    {
        if ($cell === null) {
            return '—';
        }

        $parts = [ImplementationStatus::from($cell['s'])->label().($cell['d'] ? ' ≠' : '')];
        if ($cell['r'] > 0) {
            $parts[] = $cell['r'].' RM';
        }
        if ($cell['i'] > 0) {
            $parts[] = $cell['i'].' PB';
        }

        return implode(' · ', $parts);
    }

    /**
     * What is compared with the pivot: attached or not, status, presence of a deviation.
     *
     * @param  array{id: int, s: string, d: bool, r: int, i: int}|null  $cell
     */
    private function signature(?array $cell): string
    {
        return $cell === null ? '-' : $cell['s'].($cell['d'] ? '+d' : '');
    }
}
