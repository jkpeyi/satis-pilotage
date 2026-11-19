<?php

namespace App\Models\Concerns;

use App\Support\CatalogName;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps name_key in sync with name. Active catalog items have unique keys across modules and
 * features (EnsureUniqueCatalogName); the database enforces it within each table.
 *
 * @mixin Model
 */
trait HasNameKey
{
    public static function bootHasNameKey(): void
    {
        static::saving(function (Model $model): void {
            $model->setAttribute('name_key', CatalogName::key((string) $model->getAttribute('name')));
        });
    }
}
