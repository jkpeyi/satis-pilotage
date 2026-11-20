<?php

namespace App\Diagnostics;

use App\Diagnostics\Parsers\EslintParser;
use App\Diagnostics\Parsers\PhpstanParser;
use App\Diagnostics\Parsers\SarifParser;
use App\Enums\ToolResultFormat;
use JsonException;

/**
 * Reads a tool results file (L4). Tools run outside the platform; only their output is imported.
 */
abstract class ToolResultParser
{
    private const MESSAGE_LIMIT = 5000;

    abstract public function parse(string $contents): ParsedReport;

    public static function for(ToolResultFormat $format): self
    {
        return match ($format) {
            ToolResultFormat::Sarif => new SarifParser,
            ToolResultFormat::Phpstan => new PhpstanParser,
            ToolResultFormat::Eslint => new EslintParser,
        };
    }

    /**
     * @return array<mixed>
     */
    protected function decode(string $contents, string $formatLabel): array
    {
        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidToolResultFile("Le fichier n’est pas un JSON valide ({$formatLabel} attendu).");
        }

        if (! is_array($data)) {
            throw new InvalidToolResultFile("Le fichier ne correspond pas au format {$formatLabel}.");
        }

        return $data;
    }

    protected function message(mixed $text): string
    {
        $message = trim((string) preg_replace('/\s+/u', ' ', is_scalar($text) ? (string) $text : ''));

        return mb_strlen($message) > self::MESSAGE_LIMIT ? mb_substr($message, 0, self::MESSAGE_LIMIT - 1).'…' : $message;
    }

    protected function line(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    protected function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
