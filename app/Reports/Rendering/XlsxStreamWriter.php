<?php

namespace App\Reports\Rendering;

use RuntimeException;
use XMLWriter;
use ZipArchive;

/**
 * Minimal streaming XLSX writer for report annexes: text cells only, a bold frozen header row and
 * column widths estimated from the first rows. Rows are written to disk as they come, so memory
 * does not grow with the number of rows (PhpSpreadsheet holds the whole workbook, ~5 KB per row).
 */
final class XlsxStreamWriter
{
    private const MAX_CELL_LENGTH = 32767;

    private const WIDTH_SAMPLE_ROWS = 200;

    /** @var list<array{name: string, file: string}> */
    private array $sheets = [];

    /** @var list<string> */
    private array $takenNames = [];

    private readonly string $workDirectory;

    public function __construct()
    {
        $this->workDirectory = sys_get_temp_dir().'/xlsx-'.bin2hex(random_bytes(8));
        if (! mkdir($this->workDirectory, 0700) && ! is_dir($this->workDirectory)) {
            throw new RuntimeException('Impossible de créer le dossier de travail de l’export XLSX.');
        }
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<string>>  $rows
     */
    public function addSheet(string $name, array $headings, array $rows): void
    {
        $file = $this->workDirectory.'/sheet'.(count($this->sheets) + 1).'.xml';

        $xml = new XMLWriter;
        $xml->openUri($file);
        $xml->startDocument('1.0', 'UTF-8', 'yes');
        $xml->startElement('worksheet');
        $xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $xml->startElement('sheetViews');
        $xml->startElement('sheetView');
        $xml->writeAttribute('workbookViewId', '0');
        $xml->startElement('pane');
        $xml->writeAttribute('ySplit', '1');
        $xml->writeAttribute('topLeftCell', 'A2');
        $xml->writeAttribute('activePane', 'bottomLeft');
        $xml->writeAttribute('state', 'frozen');
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();

        $xml->startElement('cols');
        foreach ($this->columnWidths($headings, $rows) as $index => $width) {
            $xml->startElement('col');
            $xml->writeAttribute('min', (string) ($index + 1));
            $xml->writeAttribute('max', (string) ($index + 1));
            $xml->writeAttribute('width', (string) $width);
            $xml->writeAttribute('customWidth', '1');
            $xml->endElement();
        }
        $xml->endElement();

        $xml->startElement('sheetData');
        $this->writeRow($xml, 1, $headings, style: 1);
        $number = 1;
        foreach ($rows as $row) {
            $this->writeRow($xml, ++$number, $row, style: 0);
            if ($number % 1000 === 0) {
                $xml->flush();
            }
        }
        $xml->endElement();

        $xml->endElement();
        $xml->endDocument();
        $xml->flush();

        $this->sheets[] = ['name' => $this->uniqueName($name), 'file' => $file];
    }

    public function save(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible d’écrire le fichier XLSX.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            .'</styleSheet>');

        foreach ($this->sheets as $index => $sheet) {
            $zip->addFile($sheet['file'], 'xl/worksheets/sheet'.($index + 1).'.xml');
        }

        $zip->close();
        $this->cleanUp();
    }

    public function __destruct()
    {
        $this->cleanUp();
    }

    /**
     * @param  list<string>  $values
     */
    private function writeRow(XMLWriter $xml, int $number, array $values, int $style): void
    {
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $number);

        foreach ($values as $index => $value) {
            $value = $this->clean((string) $value);
            if ($value === '' && $style === 0) {
                continue;
            }

            $xml->startElement('c');
            $xml->writeAttribute('r', $this->columnLetter($index).$number);
            $xml->writeAttribute('t', 'inlineStr');
            if ($style !== 0) {
                $xml->writeAttribute('s', (string) $style);
            }
            $xml->startElement('is');
            $xml->startElement('t');
            $xml->writeAttribute('xml:space', 'preserve');
            $xml->text($value);
            $xml->endElement();
            $xml->endElement();
            $xml->endElement();
        }

        $xml->endElement();
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<string>>  $rows
     * @return list<int>
     */
    private function columnWidths(array $headings, array $rows): array
    {
        $widths = array_map(fn (string $heading): int => mb_strlen($heading), $headings);

        foreach (array_slice($rows, 0, self::WIDTH_SAMPLE_ROWS) as $row) {
            foreach ($row as $index => $value) {
                $longestLine = max(array_map('mb_strlen', explode("\n", (string) $value)));
                $widths[$index] = max($widths[$index] ?? 0, $longestLine);
            }
        }

        return array_map(fn (int $width): int => min(max($width + 2, 8), 60), $widths);
    }

    private function columnLetter(int $index): string
    {
        $letters = '';
        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $letters = chr(65 + ($index - 1) % 26).$letters;
        }

        return $letters;
    }

    private function clean(string $value): string
    {
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);

        return mb_substr($value, 0, self::MAX_CELL_LENGTH);
    }

    /**
     * Sheet names: at most 31 characters, none of []:*?/\ and unique (case-insensitive).
     */
    private function uniqueName(string $name): string
    {
        $base = trim((string) preg_replace('/[\\\\\/?*\[\]:]/', ' ', $name)) ?: 'Feuille';
        $candidate = mb_substr($base, 0, 31);
        for ($i = 2; in_array(mb_strtolower($candidate), $this->takenNames, true); $i++) {
            $candidate = mb_substr($base, 0, 31 - strlen(' '.$i)).' '.$i;
        }
        $this->takenNames[] = mb_strtolower($candidate);

        return $candidate;
    }

    private function contentTypes(): string
    {
        $sheets = '';
        foreach (array_keys($this->sheets) as $index) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet'.($index + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$sheets
            .'</Types>';
    }

    private function workbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $index => $sheet) {
            $sheets .= '<sheet name="'.htmlspecialchars($sheet['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets></workbook>';
    }

    private function workbookRelationships(): string
    {
        $relationships = '';
        foreach (array_keys($this->sheets) as $index) {
            $relationships .= '<Relationship Id="rId'.($index + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($index + 1).'.xml"/>';
        }
        $styles = count($this->sheets) + 1;
        $relationships .= '<Relationship Id="rId'.$styles.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>';
    }

    private function cleanUp(): void
    {
        if (! is_dir($this->workDirectory)) {
            return;
        }

        foreach (glob($this->workDirectory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->workDirectory);
    }
}
