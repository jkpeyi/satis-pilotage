<?php

namespace App\Diagnostics;

/**
 * Content of a tool results file: the tool, when the file names it, and its results.
 */
final readonly class ParsedReport
{
    /**
     * @param  list<ParsedResult>  $results
     */
    public function __construct(
        public ?string $toolName,
        public ?string $toolVersion,
        public array $results,
    ) {}
}
