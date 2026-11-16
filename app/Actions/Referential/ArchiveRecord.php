<?php

namespace App\Actions\Referential;

use App\Models\BusinessRule;
use App\Models\CodeSnippet;
use App\Models\Customer;
use App\Models\Environment;
use App\Models\Issue;
use App\Models\Product;
use App\Models\Repository;

/**
 * Archives or restores a record (RG-12). Versions use ArchiveVersion, catalog items ArchiveCatalogItem.
 */
final class ArchiveRecord
{
    public function handle(Product|Customer|Repository|Environment|BusinessRule|Issue|CodeSnippet $record, bool $archive = true): void
    {
        $archive ? $record->archive() : $record->unarchive();
    }
}
