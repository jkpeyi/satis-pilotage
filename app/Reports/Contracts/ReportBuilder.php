<?php

namespace App\Reports\Contracts;

use App\Reports\Content\ReportContent;
use App\Reports\ReportScope;

/**
 * Builds the content of one deliverable (L1 to L8) from the central data, without any extra input.
 */
interface ReportBuilder
{
    public function build(ReportScope $scope): ReportContent;
}
