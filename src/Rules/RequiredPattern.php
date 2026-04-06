<?php

namespace TheShit\Review\Rules;

use TheShit\Review\Contracts\Rule;
use TheShit\Review\Enums\Severity;
use TheShit\Review\Finding;

final readonly class RequiredPattern implements Rule
{
    /**
     * @param  string  $pattern  Regex pattern that should match in the added lines
     * @param  string  $message  Human-readable explanation of what's required
     * @param  string|null  $appliesTo  Glob pattern for files this rule applies to
     * @param  string|null  $context  Regex — only enforce when this pattern is also present in the added lines
     * @param  float  $confidence  Confidence score for findings
     */
    public function __construct(
        private string $pattern,
        private string $message,
        private ?string $appliesTo = null,
        private ?string $context = null,
        private float $confidence = 0.85,
    ) {}

    public function check(string $file, array $addedLines): array
    {
        if ($this->appliesTo !== null && ! fnmatch($this->appliesTo, $file)) {
            return [];
        }

        if ($addedLines === []) {
            return [];
        }

        $allAdded = implode("\n", $addedLines);

        // If context is set, only check when the context pattern is present
        if ($this->context !== null && ! preg_match($this->context, $allAdded)) {
            return [];
        }

        // Check if the required pattern exists in the added lines
        if (preg_match($this->pattern, $allAdded)) {
            return [];
        }

        // Pattern not found — violation
        $firstLine = array_key_first($addedLines);

        return [
            new Finding(
                severity: Severity::Convention,
                confidence: $this->confidence,
                file: $file,
                lines: [$firstLine],
                message: $this->message,
            ),
        ];
    }

    public function description(): string
    {
        return $this->message;
    }
}
