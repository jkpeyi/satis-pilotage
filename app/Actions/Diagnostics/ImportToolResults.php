<?php

namespace App\Actions\Diagnostics;

use App\Diagnostics\InvalidToolResultFile;
use App\Diagnostics\ParsedResult;
use App\Diagnostics\ToolResultParser;
use App\Enums\ToolResultFormat;
use App\Models\DiagnosticCampaign;
use App\Models\DiagnosticFinding;
use App\Models\ToolRun;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Imports a results file of a tool run outside the platform (L4, RG-18). A file is imported once
 * per campaign; a result already known in the campaign (same fingerprint) is not duplicated, its
 * occurrences grow. Every new result arrives "to verify": nothing becomes a problem without a
 * person's decision.
 */
final class ImportToolResults
{
    private const CHUNK = 500;

    /**
     * @param  array{tool_name: ?string, tool_version: ?string, scanned_on: string, path_prefix: ?string}  $options
     */
    public function handle(DiagnosticCampaign $campaign, ToolResultFormat $format, string $contents, string $fileName, array $options): ToolRun
    {
        if ($campaign->isArchived()) {
            throw new DomainException('Cette campagne est archivée : restaurez-la avant d’importer des résultats.');
        }

        $sha256 = hash('sha256', $contents);
        $previous = ToolRun::query()->where('diagnostic_campaign_id', $campaign->id)->where('file_sha256', $sha256)->first();
        if ($previous !== null) {
            throw new DomainException(sprintf('Ce fichier a déjà été importé dans cette campagne le %s (%s).', $previous->created_at?->format('d/m/Y'), $previous->file_name));
        }

        $report = ToolResultParser::for($format)->parse($contents);
        $toolName = $options['tool_name'] ?? $report->toolName ?? $format->defaultToolName();
        if ($toolName === null) {
            throw new InvalidToolResultFile('Le fichier ne nomme pas l’outil : indiquez son nom.');
        }

        // Fingerprint => [result, count in this file].
        $byFingerprint = [];
        foreach ($report->results as $result) {
            $result = $this->relativePath($result, $options['path_prefix']);
            $fingerprint = $this->fingerprint($toolName, $result);
            $byFingerprint[$fingerprint] ??= [$result, 0];
            $byFingerprint[$fingerprint][1]++;
        }

        return DB::transaction(function () use ($campaign, $format, $fileName, $options, $sha256, $report, $toolName, $byFingerprint): ToolRun {
            $run = ToolRun::query()->create([
                'diagnostic_campaign_id' => $campaign->id,
                'tool_name' => mb_substr($toolName, 0, 100),
                'tool_version' => $options['tool_version'] ?? ($report->toolVersion !== null ? mb_substr($report->toolVersion, 0, 64) : null),
                'format' => $format,
                'scanned_on' => $options['scanned_on'],
                'file_name' => mb_substr($fileName, 0, 255),
                'file_sha256' => $sha256,
                'results_count' => count($report->results),
                'new_count' => 0,
                'duplicate_count' => 0,
            ]);

            $userId = Auth::id();
            $now = now();
            $new = 0;

            foreach (array_chunk($byFingerprint, self::CHUNK, preserve_keys: true) as $chunk) {
                $known = DiagnosticFinding::query()
                    ->where('diagnostic_campaign_id', $campaign->id)
                    ->whereIn('fingerprint', array_keys($chunk))
                    ->pluck('id', 'fingerprint');

                $rows = [];
                foreach ($chunk as $fingerprint => [$result, $count]) {
                    if (isset($known[$fingerprint])) {
                        DiagnosticFinding::query()->whereKey($known[$fingerprint])->increment('occurrences', $count);

                        continue;
                    }

                    $rows[] = [
                        'diagnostic_campaign_id' => $campaign->id,
                        'tool_run_id' => $run->id,
                        'fingerprint' => $fingerprint,
                        'rule_id' => $result->ruleId !== null ? mb_substr($result->ruleId, 0, 255) : null,
                        'level' => $result->level->value,
                        'message' => $result->message,
                        'path' => $result->path !== null ? mb_substr($result->path, 0, 1000) : null,
                        'line_start' => $result->lineStart,
                        'line_end' => $result->lineEnd,
                        'occurrences' => $count,
                        'status' => 'to_verify',
                        'created_by' => $userId,
                        'updated_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DiagnosticFinding::query()->insert($rows);
                    $new += count($rows);
                }
            }

            $run->update(['new_count' => $new, 'duplicate_count' => count($report->results) - $new]);

            return $run;
        });
    }

    /**
     * Paths are kept relative to the repository root: the given prefix (the directory where the
     * tool ran) is removed.
     */
    private function relativePath(ParsedResult $result, ?string $prefix): ParsedResult
    {
        if ($result->path === null) {
            return $result;
        }

        $path = str_replace('\\', '/', $result->path);
        $prefix = $prefix !== null ? rtrim(str_replace('\\', '/', $prefix), '/').'/' : null;
        if ($prefix !== null && str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix));
        }
        $path = (string) preg_replace('#^(\./)+#', '', $path);

        return new ParsedResult($result->ruleId, $result->level, $result->message, $path, $result->lineStart, $result->lineEnd, $result->toolFingerprint);
    }

    /**
     * Same tool, same rule, same place and same message: the same result. The tool's own
     * fingerprint, when it gives one, is preferred.
     */
    private function fingerprint(string $toolName, ParsedResult $result): string
    {
        $parts = $result->toolFingerprint !== null
            ? [mb_strtolower($toolName), $result->ruleId, $result->toolFingerprint]
            : [mb_strtolower($toolName), $result->ruleId, $result->path, $result->lineStart, mb_strtolower($result->message)];

        return hash('sha256', (string) json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
