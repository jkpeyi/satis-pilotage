<?php

namespace App\Exceptions;

use App\Models\Feature;
use App\Models\Module;
use DomainException;

/**
 * A catalog name is already used by an active module or feature (names are unique across the
 * whole catalog, ignoring case, accents and punctuation). Carries the existing item so the UI can
 * offer to use it instead.
 */
final class DuplicateCatalogItem extends DomainException
{
    public function __construct(public readonly Module|Feature $existing, public readonly string $path)
    {
        parent::__construct(sprintf(
            '« %s » existe déjà dans le catalogue (%s : %s). Utilisez cet élément au lieu d’en créer un autre.',
            $existing->name,
            $existing instanceof Module ? ($existing->parent_id !== null ? 'sous-module' : 'module') : 'fonctionnalité',
            $path,
        ));
    }
}
