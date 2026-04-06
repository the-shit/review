<?php

namespace TheShit\Review\Rules;

use TheShit\Review\Contracts\Rule;
use TheShit\Review\Enums\Severity;
use TheShit\Review\Finding;

final readonly class ForbiddenPattern implements Rule
{
    /**
     * @param  string  $pattern  Literal string or regex (if wrapped in delimiters) to forbid
     * @param  string  $message  Human-readable explanation of why this is forbidden
     * @param  string|null  $appliesTo  Glob pattern for files this rule applies to (null = all files)
     * @param  float  $confidence  Confidence score for findings (default 0.9)
     */
    public function __construct(
        private string $pattern,
        private string $message,
        private ?string $appliesTo = null,
        private float $confidence = 0.9,
    ) {}

    public function check(string $file, array $addedLines): array
    {
        if ($this->appliesTo !== null && ! fnmatch($this->appliesTo, $file)) {
            return [];
        }

        $findings = [];
        $isRegex = $this->isRegex($this->pattern);

        foreach ($addedLines as $lineNumber => $line) {
            $matches = $isRegex
                ? (bool) preg_match($this->pattern, $line)
                : str_contains($line, $this->pattern);

            if ($matches) {
                $findings[] = new Finding(
                    severity: Severity::Convention,
                    confidence: $this->confidence,
                    file: $file,
                    lines: [$lineNumber],
                    message: $this->message,
                );
            }
        }

        return $findings;
    }

    public function description(): string
    {
        return $this->message;
    }

    private function isRegex(string $pattern): bool
    {
        return strlen($pattern) >= 2
            && $pattern[0] === $pattern[strlen($pattern) - 1]
            && in_array($pattern[0], ['/', '#', '~']);
    }
}
