<?php

namespace App\Reports\Rendering;

use App\Reports\Content\ReportContent;
use App\Reports\Content\TableSection;
use App\Reports\Content\TextSection;
use LogicException;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\TblWidth;

/**
 * Fills a deliverable's Word template: control block, text sections and tables.
 * Every value goes through XML escaping; a template variable left unresolved is an error.
 */
final class DocxRenderer
{
    private const BANNER = 'MODÈLE D’EXPORT • V1.0 • Champs entre {{ }} à renseigner automatiquement depuis l’application.';

    private const HEADER_FILL = 'D9E2F3';

    private const MISSING_COLOR = '9C5700';

    /**
     * @param  array<string, string>  $control  values of the document.* and scope.* variables
     */
    public function render(string $templatePath, ReportContent $content, array $control, string $targetPath, string $templateVersion): void
    {
        Settings::setOutputEscapingEnabled(true);

        $processor = new ReportTemplateProcessor($templatePath);
        $processor->setMacroChars('{{', '}}');

        $processor->replaceLiteral(
            self::BANNER,
            $this->escape(sprintf('EXPORT GÉNÉRÉ PAR SATIS PILOTAGE • MODÈLE V%s • « %s » signale une donnée absente de l’application.', $templateVersion, $this->missing())),
        );

        $provided = [...array_keys($control), ...array_keys($content->sections()), 'document.pending_items'];
        $unresolved = array_diff($processor->remainingVariables(), $provided);
        if ($unresolved !== []) {
            throw new LogicException('Variables non résolues dans '.basename($templatePath).' : '.implode(', ', $unresolved));
        }

        $pendingItems = $content->pendingItems();
        $processor->replaceParagraph('document.pending_items', $pendingItems === []
            ? $this->paragraph('Aucune donnée à confirmer.')
            : $this->paragraph('Données à confirmer avant diffusion :').implode('', array_map(fn (string $item): string => $this->bullet($item), $pendingItems)));

        foreach ($control as $variable => $value) {
            $processor->setValue($variable, $this->clean($value));
        }

        $images = [];
        foreach ($content->sections() as $variable => $section) {
            $xml = $section instanceof TableSection ? $this->table($processor, $section) : $this->text($section);

            foreach ($content->figures($variable) as $figure) {
                $placeholder = 'report_figure_'.count($images);
                $xml .= $this->paragraph($figure['caption'], italic: true).'<w:p><w:r><w:t>{{'.$placeholder.'}}</w:t></w:r></w:p>';
                $images[$placeholder] = $figure;
            }

            $processor->replaceParagraph($variable, $xml);
        }

        $temporaryFiles = [];
        try {
            foreach ($images as $placeholder => $figure) {
                $file = tempnam(sys_get_temp_dir(), 'figure').'.png';
                file_put_contents($file, $figure['png']);
                $temporaryFiles[] = $file;
                $processor->setImageValue($placeholder, ['path' => $file, 'width' => sprintf('%.1fcm', $figure['width_cm']), 'height' => '']);
            }

            $processor->saveAs($targetPath);
        } finally {
            foreach ($temporaryFiles as $file) {
                @unlink($file);
                @unlink(substr($file, 0, -4));
            }
        }
    }

    private function text(TextSection $section): string
    {
        if ($section->isEmpty()) {
            return $this->paragraph($this->missing(), italic: true);
        }

        return implode('', array_map(
            fn (array $block): string => $block['type'] === 'li' ? $this->bullet($block['text']) : $this->paragraph($block['text']),
            $section->blocks(),
        ));
    }

    private function table(ReportTemplateProcessor $processor, TableSection $section): string
    {
        $rows = $section->rows();
        if ($rows === []) {
            return $this->paragraph('Aucune donnée enregistrée dans l’application pour ce périmètre.', italic: true);
        }

        $max = (int) config('reports.docx_max_rows');
        $columns = $section->documentColumns();
        $size = count($columns) > 8 ? 7 : 8;
        $shown = array_slice($rows, 0, $max);

        $table = new Table([
            'borderSize' => 4,
            'borderColor' => 'A6A6A6',
            'cellMargin' => 50,
            'unit' => TblWidth::PERCENT,
            'width' => 100 * 50,
        ]);

        $header = $table->addRow(null, ['tblHeader' => true, 'cantSplit' => true]);
        foreach ($columns as $column) {
            $header->addCell(null, ['bgColor' => self::HEADER_FILL])->addText($column['label'], ['bold' => true, 'size' => $size]);
        }

        foreach ($shown as $row) {
            $tableRow = $table->addRow(null, ['cantSplit' => true]);
            foreach ($columns as $column) {
                $value = $row[$column['key']];
                $cell = $tableRow->addCell();
                if ($value !== null) {
                    foreach (explode("\n", $value) as $line) {
                        $cell->addText($this->clean($line), ['size' => $size]);
                    }
                } elseif ($column['required']) {
                    $cell->addText($this->missing(), ['size' => $size, 'italic' => true, 'color' => self::MISSING_COLOR]);
                } else {
                    $cell->addText('—', ['size' => $size]);
                }
            }
        }

        $xml = $processor->tableXml($table);

        if (count($rows) > count($shown)) {
            $xml .= $this->paragraph(sprintf('Extrait : %d premières lignes sur %d. La liste complète figure dans l’annexe XLSX.', count($shown), count($rows)), italic: true);
        }

        return $xml;
    }

    private function paragraph(string $text, bool $italic = false): string
    {
        $runProperties = $italic ? '<w:rPr><w:i/></w:rPr>' : '';

        return '<w:p><w:r>'.$runProperties.'<w:t xml:space="preserve">'.$this->escape($text).'</w:t></w:r></w:p>';
    }

    private function bullet(string $text): string
    {
        return '<w:p><w:pPr><w:pStyle w:val="ListBullet"/></w:pPr><w:r><w:t xml:space="preserve">'.$this->escape($text).'</w:t></w:r></w:p>';
    }

    private function escape(string $text): string
    {
        // Control characters are invalid in XML 1.0 and would corrupt the document.
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text);

        return htmlspecialchars($this->clean($text), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Removes control characters (invalid in XML 1.0, they would corrupt the document) and splits
     * any "{{" typed by a user with a zero-width space so it is never taken for a template variable.
     */
    private function clean(string $text): string
    {
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text);

        return str_replace('{{', "{\u{200B}{", $text);
    }

    private function missing(): string
    {
        return (string) config('reports.missing_value');
    }
}
