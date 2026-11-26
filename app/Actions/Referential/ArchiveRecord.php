<?php

namespace App\Actions\Referential;

use App\Models\AuditPeriod;
use App\Models\BusinessRule;
use App\Models\CodeSnippet;
use App\Models\Component;
use App\Models\Customer;
use App\Models\DiagnosticCampaign;
use App\Models\Environment;
use App\Models\Issue;
use App\Models\Product;
use App\Models\RefactoringStrategy;
use App\Models\RemediationAction;
use App\Models\Repository;
use App\Models\TestRun;
use App\Models\Workflow;
use App\Models\WorkPackage;

/**
 * Archives or restores a record (RG-12). Versions use ArchiveVersion, workflow steps ArchiveWorkflowStep, catalog items ArchiveCatalogItem.
 */
final class ArchiveRecord
{
    public function handle(Product|Customer|Repository|Environment|BusinessRule|Issue|CodeSnippet|Workflow|Component|DiagnosticCampaign|TestRun|AuditPeriod|WorkPackage|RemediationAction|RefactoringStrategy $record, bool $archive = true): void
    {
        $archive ? $record->archive() : $record->unarchive();
    }
}
