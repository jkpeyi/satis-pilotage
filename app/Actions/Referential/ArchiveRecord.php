<?php

namespace App\Actions\Referential;

use App\Models\BusinessRule;
use App\Models\CodeSnippet;
use App\Models\Component;
use App\Models\Customer;
use App\Models\Environment;
use App\Models\Integration;
use App\Models\Issue;
use App\Models\Product;
use App\Models\Release;
use App\Models\Repository;
use App\Models\Workflow;

/**
 * Archives or restores a record (RG-12). Versions use ArchiveVersion, deployments ArchiveDeployment, workflow steps ArchiveWorkflowStep, catalog items ArchiveCatalogItem.
 */
final class ArchiveRecord
{
    public function handle(Product|Customer|Repository|Release|Environment|BusinessRule|Issue|CodeSnippet|Workflow|Component|Integration $record, bool $archive = true): void
    {
        $archive ? $record->archive() : $record->unarchive();
    }
}
