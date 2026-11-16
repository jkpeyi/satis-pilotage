<?php

namespace App\Exceptions;

use DomainException;

/**
 * Detaching items that carry business rules or issues needs an explicit confirmation (RG-12).
 */
final class DetachRequiresConfirmation extends DomainException
{
    public function __construct(public readonly int $rulesCount, public readonly int $issuesCount)
    {
        parent::__construct(sprintf(
            'Les éléments à détacher portent %d règle(s) métier et %d problème(s). Confirmez le détachement : ils seront archivés avec leur rattachement.',
            $rulesCount,
            $issuesCount,
        ));
    }
}
