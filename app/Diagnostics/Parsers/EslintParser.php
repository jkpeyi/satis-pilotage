<?php

namespace App\Diagnostics\Parsers;

use App\Diagnostics\InvalidToolResultFile;
use App\Diagnostics\ParsedReport;
use App\Diagnostics\ParsedResult;
use App\Diagnostics\ToolResultParser;
use App\Enums\FindingLevel;

/**
 * ESLint run with --format json: one entry per file, severity 2 for an error and 1 for a warning.
 * The file does not give the tool version.
 */
final class EslintParser extends ToolResultParser
{
    public function parse(string $contents): ParsedReport
    {
        $data = $this->decode($contents, 'ESLint JSON');
        if (! array_is_list($data)) {
            throw new InvalidToolResultFile('Le fichier ne correspond pas au format ESLint JSON : une liste de fichiers est attendue.');
        }

        $results = [];
        foreach ($data as $file) {
            if (! is_array($file) || ! array_key_exists('filePath', $file)) {
                throw new InvalidToolResultFile('Le fichier ne correspond pas au format ESLint JSON : « filePath » est absent.');
            }

            foreach (is_array($file['messages'] ?? null) ? $file['messages'] : [] as $message) {
                if (! is_array($message)) {
                    continue;
                }
                $line = $this->line($message['line'] ?? null);
                $results[] = new ParsedResult(
                    ruleId: $this->string($message['ruleId'] ?? null),
                    level: (int) ($message['severity'] ?? 1) >= 2 ? FindingLevel::Error : FindingLevel::Warning,
                    message: $this->message($message['message'] ?? ''),
                    path: $this->string($file['filePath']),
                    lineStart: $line,
                    lineEnd: $this->line($message['endLine'] ?? null) ?? $line,
                );
            }
        }

        return new ParsedReport(null, null, $results);
    }
}
