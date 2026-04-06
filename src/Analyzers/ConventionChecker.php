<?php

namespace TheShit\Review\Analyzers;

use TheShit\Review\Contracts\Rule;
use TheShit\Review\Finding;

final class ConventionChecker
{
    /** @var Rule[] */
    private array $rules = [];

    /**
     * @param  Rule[]  $rules
     */
    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }

    public function addRule(Rule $rule): self
    {
        $this->rules[] = $rule;

        return $this;
    }

    /**
     * Check a file's diff patch against all rules.
     *
     * @param  string  $file  The file path
     * @param  string  $patch  The unified diff patch for this file
     * @return Finding[]
     */
    public function check(string $file, string $patch): array
    {
        $addedLines = $this->extractAddedLines($patch);

        if ($addedLines === []) {
            return [];
        }

        $findings = [];

        foreach ($this->rules as $rule) {
            $findings = [...$findings, ...$rule->check($file, $addedLines)];
        }

        return $findings;
    }

    /**
     * Check multiple files at once.
     *
     * @param  array<string, string>  $patches  Map of file path => patch content
     * @return Finding[]
     */
    public function checkAll(array $patches): array
    {
        $findings = [];

        foreach ($patches as $file => $patch) {
            $findings = [...$findings, ...$this->check($file, $patch)];
        }

        return $findings;
    }

    /**
     * Extract only the added lines from a unified diff patch.
     * Returns line number => line content (without the leading +).
     *
     * @return array<int, string>
     */
    private function extractAddedLines(string $patch): array
    {
        $lines = explode("\n", $patch);
        $added = [];
        $currentLine = 0;

        foreach ($lines as $line) {
            // Parse hunk header to get line numbers
            if (preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)/', $line, $m)) {
                $currentLine = (int) $m[1];

                continue;
            }

            if (str_starts_with($line, '+') && ! str_starts_with($line, '+++')) {
                $added[$currentLine] = substr($line, 1);
                $currentLine++;
            } elseif (str_starts_with($line, '-') && ! str_starts_with($line, '---')) {
                // Removed line — don't increment current line
                continue;
            } else {
                // Context line
                $currentLine++;
            }
        }

        return $added;
    }
}
