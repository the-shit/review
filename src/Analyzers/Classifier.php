<?php

namespace TheShit\Review\Analyzers;

use TheShit\Review\Enums\Classification;

final class Classifier
{
    private const array TITLE_PATTERNS = [
        Classification::BugFix->value => ['fix', 'bug', 'patch', 'hotfix', 'bugfix'],
        Classification::Refactor->value => ['refactor', 'rename', 'extract', 'cleanup', 'clean up', 'reorganize'],
        Classification::Docs->value => ['doc', 'readme', 'changelog', 'typo'],
        Classification::Dependency->value => ['bump', 'upgrade', 'dependabot', 'renovate', 'composer update'],
        Classification::Chore->value => ['chore', 'ci', 'lint', 'format', 'style'],
        Classification::Feature->value => ['feat', 'add', 'implement', 'introduce', 'support'],
    ];

    /**
     * @param  string[]  $filePaths
     */
    public function classify(string $title, string $description, array $filePaths): Classification
    {
        return $this->fromTitle($title)
            ?? $this->fromFiles($filePaths)
            ?? Classification::Feature;
    }

    private function fromTitle(string $title): ?Classification
    {
        $lower = strtolower($title);

        // Conventional commit prefix takes priority
        if (preg_match('/^(fix|feat|refactor|docs|chore|ci|style|build|perf|test)[\(:]/', $lower, $match)) {
            return match ($match[1]) {
                'fix' => Classification::BugFix,
                'feat' => Classification::Feature,
                'refactor', 'perf' => Classification::Refactor,
                'docs' => Classification::Docs,
                'chore', 'ci', 'style', 'build' => Classification::Chore,
                'test' => Classification::Chore,
                default => null,
            };
        }

        foreach (self::TITLE_PATTERNS as $classification => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return Classification::from($classification);
                }
            }
        }

        return null;
    }

    /**
     * @param  string[]  $filePaths
     */
    private function fromFiles(array $filePaths): ?Classification
    {
        if ($filePaths === []) {
            return null;
        }

        $allDocs = true;
        $allDeps = true;
        $allConfig = true;

        foreach ($filePaths as $path) {
            $lower = strtolower($path);
            $ext = pathinfo($lower, PATHINFO_EXTENSION);

            if (! in_array($ext, ['md', 'txt', 'rst']) && ! str_starts_with($lower, 'docs/')) {
                $allDocs = false;
            }

            if (! in_array(basename($lower), ['composer.lock', 'package-lock.json', 'yarn.lock', 'pnpm-lock.yaml'])) {
                $allDeps = false;
            }

            if (! str_starts_with($lower, '.github/') && ! str_starts_with($lower, 'config/') && $ext !== 'yml' && $ext !== 'yaml') {
                $allConfig = false;
            }
        }

        if ($allDocs) {
            return Classification::Docs;
        }

        if ($allDeps) {
            return Classification::Dependency;
        }

        if ($allConfig) {
            return Classification::Chore;
        }

        return null;
    }
}
