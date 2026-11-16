<?php

namespace App\Contracts;

/**
 * Enums shown to users expose a French label.
 */
interface HasLabel
{
    public function label(): string;
}
