<?php

namespace App\Diagnostics;

use SimpleXMLElement;

/**
 * Reads test counts from a JUnit XML report (PHPUnit, Pest, Jest, pytest…) and line coverage from
 * a Clover or Cobertura report. Only what the report states is returned.
 */
final class TestReport
{
    /**
     * @return array{tests_total: int, tests_passed: int, tests_failed: int, tests_skipped: int}
     */
    public static function junit(string $contents): array
    {
        $xml = self::load($contents, 'JUnit');
        $suites = $xml->getName() === 'testsuite' ? [$xml] : iterator_to_array($xml->testsuite ?? [], false);
        if ($xml->getName() !== 'testsuite' && $xml->getName() !== 'testsuites') {
            throw new InvalidToolResultFile('Le fichier n’est pas un rapport JUnit : la racine doit être « testsuites » ou « testsuite ».');
        }

        $sum = fn (string $attribute): int => $xml->getName() === 'testsuites' && isset($xml[$attribute])
            ? (int) $xml[$attribute]
            : array_sum(array_map(fn (SimpleXMLElement $suite): int => (int) $suite[$attribute], $suites));

        $total = $sum('tests');
        $failed = $sum('failures') + $sum('errors');
        $skipped = $sum('skipped');

        return [
            'tests_total' => $total,
            'tests_passed' => max(0, $total - $failed - $skipped),
            'tests_failed' => $failed,
            'tests_skipped' => $skipped,
        ];
    }

    /**
     * Line coverage in percent, rounded to two decimals.
     */
    public static function coverage(string $contents): float
    {
        $xml = self::load($contents, 'Clover ou Cobertura');

        // Cobertura: <coverage line-rate="0.85">.
        if ($xml->getName() === 'coverage' && isset($xml['line-rate'])) {
            return round((float) $xml['line-rate'] * 100, 2);
        }

        // Clover: <coverage><project><metrics statements=".." coveredstatements=".."/></project></coverage>.
        $metrics = $xml->project->metrics ?? null;
        if ($xml->getName() === 'coverage' && $metrics !== null && isset($metrics['statements'])) {
            $statements = (int) $metrics['statements'];

            return $statements === 0 ? 0.0 : round((int) $metrics['coveredstatements'] * 100 / $statements, 2);
        }

        throw new InvalidToolResultFile('Le fichier n’est pas un rapport de couverture Clover ou Cobertura.');
    }

    private static function load(string $contents, string $format): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        // LIBXML_NONET: never fetch anything from the network while parsing.
        $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new InvalidToolResultFile("Le fichier n’est pas un XML valide ({$format} attendu).");
        }

        return $xml;
    }
}
