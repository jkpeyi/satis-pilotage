<?php

namespace App\Actions\Referential;

use App\Models\Customer;
use App\Models\Environment;
use App\Models\Product;
use App\Models\Repository;

/**
 * Archives or restores a referential record (RG-12). Versions use ArchiveVersion.
 */
final class ArchiveRecord
{
    public function handle(Product|Customer|Repository|Environment $record, bool $archive = true): void
    {
        $archive ? $record->archive() : $record->unarchive();
    }
}
