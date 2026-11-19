<?php

namespace App\Reports\Rendering;

use App\Exports\SimpleSheet;
use App\Reports\Content\ReportContent;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * XLSX annex of a report: a "Contrôle" sheet, then every table in full (no row limit).
 */
final class ReportAnnexExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<string, string>  $control  label => value
     */
    public function __construct(private readonly ReportContent $content, private readonly array $control) {}

    /**
     * @return list<SimpleSheet>
     */
    public function sheets(): array
    {
        $control = [];
        foreach ($this->control as $label => $value) {
            $control[] = [$label, $value];
        }

        $sheets = [new SimpleSheet('Contrôle', ['Champ', 'Valeur'], $control)];
        $titles = ['contrôle'];
        $missing = (string) config('reports.missing_value');

        foreach ($this->content->tables() as $table) {
            $rows = [];
            foreach ($table->rows() as $row) {
                $values = [];
                foreach ($table->columns() as $column) {
                    $values[] = $row[$column['key']] ?? ($column['required'] ? $missing : '');
                }
                $rows[] = $values;
            }

            $sheets[] = new SimpleSheet($this->uniqueTitle($table->title, $titles), array_column($table->columns(), 'label'), $rows);
        }

        return $sheets;
    }

    /**
     * Sheet titles: at most 31 characters, no []:*?/\ and unique (case-insensitive).
     *
     * @param  list<string>  $taken
     */
    private function uniqueTitle(string $title, array &$taken): string
    {
        $base = mb_substr(trim((string) preg_replace('/[\\\\\/?*\[\]:]/', ' ', $title)), 0, 27);
        $candidate = $base;
        for ($i = 2; in_array(mb_strtolower($candidate), $taken, true); $i++) {
            $candidate = $base.' '.$i;
        }
        $taken[] = mb_strtolower($candidate);

        return $candidate;
    }
}
