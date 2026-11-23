<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Status of a remediation action (L8).
 */
enum ActionStatus: string implements HasLabel
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'À faire',
            self::InProgress => 'En cours',
            self::Blocked => 'Bloquée',
            self::Done => 'Terminée',
            self::Cancelled => 'Annulée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Todo => 'gray',
            self::InProgress => 'blue',
            self::Blocked => 'red',
            self::Done => 'green',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Still to be done: to do, in progress or blocked.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Todo, self::InProgress, self::Blocked], true);
    }
}
