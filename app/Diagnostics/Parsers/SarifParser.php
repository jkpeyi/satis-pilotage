<?php

namespace App\Diagnostics\Parsers;

use App\Diagnostics\InvalidToolResultFile;
use App\Diagnostics\ParsedReport;
use App\Diagnostics\ParsedResult;
use App\Diagnostics\ToolResultParser;
use App\Enums\FindingLevel;

/**
 * SARIF 2.1.0, the common output of Semgrep, SonarQube, CodeQL, Trivy, Gitleaks… A level missing
 * on a result comes from its rule, then defaults to "warning" as the standard says.
 */
final class SarifParser extends ToolResultParser
{
    public function parse(string $contents): ParsedReport
    {
        $data = $this->decode($contents, 'SARIF');
        if (! isset($data['runs']) || ! is_array($data['runs'])) {
            throw new InvalidToolResultFile('Le fichier ne correspond pas au format SARIF : la liste « runs » est absente.');
        }

        $toolNames = [];
        $toolVersion = null;
        $results = [];

        foreach ($data['runs'] as $run) {
            if (! is_array($run)) {
                continue;
            }

            $driver = is_array($run['tool']['driver'] ?? null) ? $run['tool']['driver'] : [];
            if (($name = $this->string($driver['name'] ?? null)) !== null) {
                $toolNames[$name] = true;
            }
            $toolVersion ??= $this->string($driver['semanticVersion'] ?? null) ?? $this->string($driver['version'] ?? null);

            $rules = $this->rules($driver);

            foreach (is_array($run['results'] ?? null) ? $run['results'] : [] as $result) {
                if (is_array($result)) {
                    $results[] = $this->result($result, $rules);
                }
            }
        }

        return new ParsedReport($toolNames !== [] ? implode(' + ', array_keys($toolNames)) : null, $toolVersion, $results);
    }

    /**
     * @param  array<mixed>  $driver
     * @return array{byId: array<string, array<mixed>>, byIndex: list<array<mixed>>}
     */
    private function rules(array $driver): array
    {
        $byId = [];
        $byIndex = [];
        foreach (is_array($driver['rules'] ?? null) ? $driver['rules'] : [] as $rule) {
            $rule = is_array($rule) ? $rule : [];
            $byIndex[] = $rule;
            if (is_string($rule['id'] ?? null)) {
                $byId[$rule['id']] = $rule;
            }
        }

        return ['byId' => $byId, 'byIndex' => $byIndex];
    }

    /**
     * @param  array<mixed>  $result
     * @param  array{byId: array<string, array<mixed>>, byIndex: list<array<mixed>>}  $rules
     */
    private function result(array $result, array $rules): ParsedResult
    {
        $ruleId = $this->string($result['ruleId'] ?? null) ?? $this->string($result['rule']['id'] ?? null);
        $ruleIndex = $result['ruleIndex'] ?? $result['rule']['index'] ?? null;
        $rule = (is_int($ruleIndex) ? $rules['byIndex'][$ruleIndex] ?? null : null) ?? ($ruleId !== null ? $rules['byId'][$ruleId] ?? null : null) ?? [];
        $ruleId ??= $this->string($rule['id'] ?? null);

        $message = $this->string($result['message']['text'] ?? null)
            ?? $this->string($result['message']['markdown'] ?? null)
            ?? $this->string($rule['messageStrings'][$result['message']['id'] ?? '']['text'] ?? null)
            ?? $this->string($rule['shortDescription']['text'] ?? null)
            ?? $ruleId
            ?? 'Résultat sans message';

        $physical = $result['locations'][0]['physicalLocation'] ?? [];
        $physical = is_array($physical) ? $physical : [];
        $region = is_array($physical['region'] ?? null) ? $physical['region'] : [];
        $lineStart = $this->line($region['startLine'] ?? null);

        $fingerprints = is_array($result['partialFingerprints'] ?? null) && $result['partialFingerprints'] !== []
            ? $result['partialFingerprints']
            : (is_array($result['fingerprints'] ?? null) ? $result['fingerprints'] : []);
        ksort($fingerprints);
        $toolFingerprint = $fingerprints !== [] ? (string) json_encode($fingerprints) : null;

        return new ParsedResult(
            ruleId: $ruleId,
            level: $this->level($this->string($result['level'] ?? null) ?? $this->string($rule['defaultConfiguration']['level'] ?? null)),
            message: $this->message($message),
            path: $this->path($this->string($physical['artifactLocation']['uri'] ?? null)),
            lineStart: $lineStart,
            lineEnd: $this->line($region['endLine'] ?? null) ?? $lineStart,
            toolFingerprint: $toolFingerprint,
        );
    }

    private function level(?string $level): FindingLevel
    {
        return match ($level) {
            'error' => FindingLevel::Error,
            'note', 'none' => FindingLevel::Note,
            default => FindingLevel::Warning,
        };
    }

    private function path(?string $uri): ?string
    {
        if ($uri === null) {
            return null;
        }

        return rawurldecode((string) preg_replace('#^file://#', '', $uri));
    }
}
