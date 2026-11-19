<?php

namespace App\Reports\Rendering;

use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Shared\XMLWriter;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Writer\Word2007\Element\Table as TableWriter;

/**
 * TemplateProcessor with the few operations the report templates need: replace a variable's
 * paragraph with several paragraphs, or with a table followed by paragraphs, and rewrite fixed text.
 */
final class ReportTemplateProcessor extends TemplateProcessor
{
    /**
     * Replaces the paragraph holding the variable with raw WordprocessingML.
     */
    public function replaceParagraph(string $variable, string $xml): void
    {
        $this->replaceXmlBlock($variable, $xml, 'w:p');
    }

    public function tableXml(Table $table): string
    {
        $xmlWriter = new XMLWriter;
        (new TableWriter($xmlWriter, $table, false))->write();

        return $xmlWriter->getData();
    }

    /**
     * Replaces a literal text of the main document part (already XML-escaped on both sides).
     */
    public function replaceLiteral(string $search, string $replace): void
    {
        $this->tempDocumentMainPart = str_replace($search, $replace, $this->tempDocumentMainPart);
    }

    /**
     * Variables still present in the main part, header and footer.
     *
     * @return list<string>
     */
    public function remainingVariables(): array
    {
        return array_values(array_unique($this->getVariables()));
    }
}
