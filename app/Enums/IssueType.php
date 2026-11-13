<?php

namespace App\Enums;

enum IssueType: string
{
    case Bug = 'bug';
    case TechDebt = 'tech_debt';
    case Security = 'security';
    case Performance = 'performance';
    case Obsolescence = 'obsolescence';
    case Usability = 'usability';

    public function label(): string
    {
        return match ($this) {
            self::Bug => 'Bug',
            self::TechDebt => 'Dette technique',
            self::Security => 'Sécurité',
            self::Performance => 'Performance',
            self::Obsolescence => 'Obsolescence',
            self::Usability => 'Ergonomie',
        };
    }
}
