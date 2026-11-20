<?php

namespace App\Diagnostics\Parsers;

use App\Diagnostics\InvalidToolResultFile;
use App\Diagnostics\ParsedReport;
use App\Diagnostics\ParsedResult;
use App\Diagnostics\ToolResultParser;
use App\Enums\FindingLevel;

/**
 * PHPStan or Larastan run with --error-format=json. Every message is an error; general errors
 * (configuration, autoload) have no file. The file does not give the tool version.
 */
final class PhpstanParser extends ToolResultParser
{
    public function parse(string $contents): ParsedReport
    {
        $data = $this->decode($contents, 'PHPStan JSON');
        if (! isset($data['files']) || ! is_array($data['files'])) {
            throw new InvalidToolResultFile('Le fichier ne correspond pas au format PHPStan JSON : la clé « files » est absente.');
        }

        $results = [];
        foreach ($data['files'] as $path => $file) {
            foreach (is_array($file['messages'] ?? null) ? $file['messages'] : [] as $message) {
                if (! is_array($message)) {
                    continue;
                }
                $line = $this->line($message['line'] ?? null);
                $results[] = new ParsedResult(
                    ruleId: $this->string($message['identifier'] ?? null),
                    level: FindingLevel::Error,
                    message: $this->message($message['message'] ?? ''),
                    path: (string) $path,
                    lineStart: $line,
                    lineEnd: $line,
                );
            }
        }

        foreach (is_array($data['errors'] ?? null) ? $data['errors'] : [] as $error) {
            $results[] = new ParsedResult(null, FindingLevel::Error, $this->message($error));
        }

        return new ParsedReport(null, null, $results);
    }
}
