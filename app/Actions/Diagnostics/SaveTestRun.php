<?php

namespace App\Actions\Diagnostics;

use App\Models\DiagnosticCampaign;
use App\Models\TestRun;
use DomainException;

/**
 * Records a test run of a campaign (L4): counts and coverage measured outside the platform, typed
 * or read from a JUnit / Clover / Cobertura report. A value not measured stays empty.
 */
final class SaveTestRun
{
    /**
     * @param  array{tool_name: string, tool_version: ?string, executed_on: string, tests_total: ?int, tests_passed: ?int, tests_failed: ?int, tests_skipped: ?int, line_coverage: ?string, scope: ?string, source_file_name: ?string, source_sha256: ?string}  $data
     */
    public function handle(DiagnosticCampaign $campaign, array $data, ?TestRun $testRun = null): TestRun
    {
        $counted = (int) $data['tests_passed'] + (int) $data['tests_failed'] + (int) $data['tests_skipped'];
        if ($data['tests_total'] !== null && $counted > $data['tests_total']) {
            throw new DomainException('Réussis, en échec et ignorés dépassent le nombre total de tests.');
        }

        $testRun ??= new TestRun(['diagnostic_campaign_id' => $campaign->id]);
        $testRun->fill($data)->save();

        return $testRun;
    }
}
