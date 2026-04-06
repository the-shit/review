<?php

namespace TheShit\Review\Rules;

use TheShit\Review\Contracts\Rule;
use TheShit\Review\Enums\Severity;
use TheShit\Review\Finding;

final readonly class NamingConvention implements Rule
{
    /**
     * @param  string  $pattern  Regex that new class/method/variable names must match
     * @param  string  $message  Human-readable explanation
     * @param  string  $target  What to check: 'class', 'method', 'variable'
     * @param  string|null  $appliesTo  Glob pattern for files
     * @param  float  $confidence  Confidence score
     */
    public function __construct(
        private string $pattern,
        private string $message,
        private string $target = 'class',
        private ?string $appliesTo = null,
        private float $confidence = 0.85,
    ) {}

    public function check(string $file, array $addedLines): array
    {
        if ($this->appliesTo !== null && ! fnmatch($this->appliesTo, $file)) {
            return [];
        }

        $findings = [];
        $extractor = $this->extractorFor($this->target);

        foreach ($addedLines as $lineNumber => $line) {
            $names = $extractor($line);

            foreach ($names as $name) {
                if (! preg_match($this->pattern, $name)) {
                    $findings[] = new Finding(
                        severity: Severity::Convention,
                        confidence: $this->confidence,
                        file: $file,
                        lines: [$lineNumber],
                        message: "{$this->message}: `{$name}`",
                    );
                }
            }
        }

        return $findings;
    }

    public function description(): string
    {
        return $this->message;
    }

    /**
     * @return callable(string): string[]
     */
    private function extractorFor(string $target): callable
    {
        return match ($target) {
            'class' => fn (string $line): array => $this->extractClasses($line),
            'method' => fn (string $line): array => $this->extractMethods($line),
            'variable' => fn (string $line): array => $this->extractVariables($line),
            default => fn (): array => [],
        };
    }

    /** @return string[] */
    private function extractClasses(string $line): array
    {
        if (preg_match('/^\s*(?:abstract\s+|final\s+|readonly\s+)*class\s+(\w+)/', $line, $m)) {
            return [$m[1]];
        }

        return [];
    }

    /** @return string[] */
    private function extractMethods(string $line): array
    {
        if (preg_match('/^\s*(?:public|protected|private|static|\s)*function\s+(\w+)/', $line, $m)) {
            return [$m[1]];
        }

        return [];
    }

    /** @return string[] */
    private function extractVariables(string $line): array
    {
        preg_match_all('/\$(\w+)/', $line, $matches);

        // Filter out common variables that shouldn't be checked
        return array_filter($matches[1] ?? [], fn (string $v): bool => ! in_array($v, ['this', 'e', 'i', 'key', 'value']));
    }
}
