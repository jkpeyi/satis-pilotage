<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * File formats accepted for tool results (L4). Tools run outside the platform.
 */
enum ToolResultFormat: string implements HasLabel
{
    case Sarif = 'sarif';
    case Phpstan = 'phpstan';
    case Eslint = 'eslint';

    public function label(): string
    {
        return match ($this) {
            self::Sarif => 'SARIF 2.1 (Semgrep, SonarQube, CodeQL, Trivy…)',
            self::Phpstan => 'PHPStan / Larastan (--error-format=json)',
            self::Eslint => 'ESLint (--format json)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Sarif => 'SARIF',
            self::Phpstan => 'PHPStan JSON',
            self::Eslint => 'ESLint JSON',
        };
    }

    /**
     * Tool name when the file does not carry it (SARIF does).
     */
    public function defaultToolName(): ?string
    {
        return match ($this) {
            self::Sarif => null,
            self::Phpstan => 'PHPStan',
            self::Eslint => 'ESLint',
        };
    }
}
