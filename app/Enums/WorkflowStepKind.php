<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum WorkflowStepKind: string implements HasLabel
{
    case Action = 'action';
    case Decision = 'decision';
    case End = 'end';

    public function label(): string
    {
        return match ($this) {
            self::Action => 'Action',
            self::Decision => 'Décision (si / sinon)',
            self::End => 'Fin',
        };
    }
}
