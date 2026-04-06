<?php

namespace TheShit\Review\Contracts;

use TheShit\Review\Finding;

interface Rule
{
    /**
     * Check a single file's changed lines against this rule.
     *
     * @param  string  $file  The file path being checked
     * @param  string[]  $addedLines  Only the new/changed lines from the diff (not the whole file)
     * @return Finding[] Findings for any violations
     */
    public function check(string $file, array $addedLines): array;

    public function description(): string;
}
