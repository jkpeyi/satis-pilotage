<?php

namespace App\Models\Concerns;

/**
 * Stable identifier shown to users and in exports, derived from the auto-increment id (RG-13):
 * never a label, never reused. Each model declares CODE_PREFIX, e.g. "APP-".
 */
trait HasDisplayCode
{
    public function displayCode(): string
    {
        return sprintf('%s%04d', static::CODE_PREFIX, $this->getKey());
    }
}
