<?php

namespace App\Livewire\Concerns;

use App\Contracts\HasLabel;
use BackedEnum;

trait EnumOptions
{
    /**
     * value => French label, for selects.
     *
     * @param  class-string<BackedEnum&HasLabel>  $enum
     * @return array<string, string>
     */
    protected function enumOptions(string $enum): array
    {
        $options = [];

        foreach ($enum::cases() as $case) {
            $options[(string) $case->value] = $case->label();
        }

        return $options;
    }
}
