<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Comparison key of a catalog name: case, accents, punctuation and repeated spaces are ignored,
 * so "Crédit", "credit" and "CRÉDIT " are the same name.
 */
final class CatalogName
{
    public static function key(string $name): string
    {
        $ascii = Str::lower(Str::ascii($name));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $ascii));
    }
}
