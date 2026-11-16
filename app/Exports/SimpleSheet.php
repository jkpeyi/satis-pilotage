<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * A titled sheet with a bold header row.
 */
final class SimpleSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<string>>  $rows
     */
    public function __construct(private readonly string $title, private readonly array $headings, private readonly array $rows) {}

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [1 => ['font' => ['bold' => true]]];
    }
}
