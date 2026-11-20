<?php

namespace App\Diagnostics;

use App\Enums\FindingLevel;

/**
 * One result read from a tool file. Code excerpts carried by the file are never kept: they can
 * hold secrets or personal data.
 */
final readonly class ParsedResult
{
    public function __construct(
        public ?string $ruleId,
        public FindingLevel $level,
        public string $message,
        public ?string $path = null,
        public ?int $lineStart = null,
        public ?int $lineEnd = null,
        // Stable identifier given by the tool (SARIF partialFingerprints), if any.
        public ?string $toolFingerprint = null,
    ) {}
}
