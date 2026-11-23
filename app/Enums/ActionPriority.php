<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Priority of a remediation action (L8).
 */
enum ActionPriority: string implements HasLabel
{
    case P1 = 'p1';
    case P2 = 'p2';
    case P3 = 'p3';
    case P4 = 'p4';

    public function label(): string
    {
        return match ($this) {
            self::P1 => 'P1 — critique',
            self::P2 => 'P2 — haute',
            self::P3 => 'P3 — moyenne',
            self::P4 => 'P4 — basse',
        };
    }

    public function shortLabel(): string
    {
        return strtoupper($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::P1 => 'red',
            self::P2 => 'amber',
            self::P3 => 'blue',
            self::P4 => 'gray',
        };
    }
}
