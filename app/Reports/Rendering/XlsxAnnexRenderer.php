<?php

namespace App\Reports\Rendering;

use App\Reports\Content\ReportContent;

/**
 * XLSX annex of a report: a "Contrôle" sheet, then every table in full (no row limit).
 */
final class XlsxAnnexRenderer
{
    /**
     * @param  array<string, string>  $control  label => value
     */
    public function render(ReportContent $content, array $control, string $targetPath): void
    {
        $writer = new XlsxStreamWriter;

        $writer->addSheet('Contrôle', ['Champ', 'Valeur'], array_map(
            fn (string $label, string $value): array => [$label, $value],
            array_keys($control),
            array_values($control),
        ));

        $missing = (string) config('reports.missing_value');

        foreach ($content->tables() as $table) {
            $rows = [];
            foreach ($table->rows() as $row) {
                $values = [];
                foreach ($table->columns() as $column) {
                    $values[] = $row[$column['key']] ?? ($column['required'] ? $missing : '');
                }
                $rows[] = $values;
            }

            $writer->addSheet($table->title, array_column($table->columns(), 'label'), $rows);
        }

        $writer->save($targetPath);
    }
}
