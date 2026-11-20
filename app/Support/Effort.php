<?php

namespace App\Support;

/**
 * Effort in days, as a single value or a range: "1,5 j/h", "2 à 4 j/h".
 */
final class Effort
{
    public static function format(float|string|null $min, float|string|null $max, string $unit = ' j/h'): ?string
    {
        if ($min === null && $max === null) {
            return null;
        }

        $number = fn (float|string $value): string => rtrim(rtrim(number_format((float) $value, 1, ',', ' '), '0'), ',');
        $low = $min ?? $max;
        $high = $max ?? $min;

        return ((float) $low === (float) $high ? $number((string) $low) : $number((string) $low).' à '.$number((string) $high)).$unit;
    }
}
