<?php

namespace TheShit\Review\Analyzers;

use TheShit\Review\Data\StructuralChange;
use TheShit\Review\Enums\ChangeType;
use TheShit\Review\Enums\Classification;
use TheShit\Review\Enums\Risk;

final class RiskAssessor
{
    /** @var string[] */
    private array $criticalPaths;

    /**
     * @param  string[]|null  $criticalPaths  Glob patterns for high-risk paths. Falls back to config.
     */
    public function __construct(?array $criticalPaths = null)
    {
        $this->criticalPaths = $criticalPaths ?? [
            'app/Providers/*',
            'config/*',
            'database/migrations/*',
            'routes/*',
        ];
    }

    /**
     * @param  string[]  $filePaths
     * @param  StructuralChange[]  $structuralChanges
     * @return array{risk: Risk, blast_radius: string[], signals: string[]}
     */
    public function assess(
        array $filePaths,
        array $structuralChanges,
        Classification $classification,
        int $totalAdditions = 0,
        int $totalDeletions = 0,
    ): array {
        $signals = [];
        $risk = Risk::Low;

        // Docs and dependency PRs are inherently low risk
        if (in_array($classification, [Classification::Docs, Classification::Dependency])) {
            return ['risk' => Risk::Low, 'blast_radius' => [], 'signals' => ['classification_low_risk']];
        }

        // File count signal
        $fileCount = count($filePaths);
        if ($fileCount >= 20) {
            $risk = $this->escalate($risk, Risk::High);
            $signals[] = "large_pr:{$fileCount}_files";
        } elseif ($fileCount >= 10) {
            $risk = $this->escalate($risk, Risk::Medium);
            $signals[] = "moderate_pr:{$fileCount}_files";
        }

        // Change volume signal
        $totalChanges = $totalAdditions + $totalDeletions;
        if ($totalChanges >= 500) {
            $risk = $this->escalate($risk, Risk::High);
            $signals[] = "high_churn:+{$totalAdditions}/-{$totalDeletions}";
        } elseif ($totalChanges >= 200) {
            $risk = $this->escalate($risk, Risk::Medium);
            $signals[] = "moderate_churn:+{$totalAdditions}/-{$totalDeletions}";
        }

        // Critical path signal
        $criticalFiles = $this->findCriticalPathMatches($filePaths);
        if ($criticalFiles !== []) {
            $risk = $this->escalate($risk, Risk::Medium);
            $signals[] = 'critical_paths:'.implode(',', array_slice($criticalFiles, 0, 3));
        }

        // Public API changes signal
        $apiChanges = array_filter($structuralChanges, fn (StructuralChange $c): bool => $c->affectsPublicApi());
        if ($apiChanges !== []) {
            $risk = $this->escalate($risk, Risk::Medium);
            $signals[] = 'api_surface_changes:'.count($apiChanges);
        }

        // Test coverage gap signal
        $codeFiles = array_filter($filePaths, fn (string $f): bool => $this->isCodeFile($f) && ! $this->isTestFile($f));
        $testFiles = array_filter($filePaths, fn (string $f): bool => $this->isTestFile($f));

        if ($codeFiles !== [] && $testFiles === []) {
            $signals[] = 'no_test_changes';
        }

        // Exception handling removal is risky
        $removedHandling = array_filter(
            $structuralChanges,
            fn (StructuralChange $c): bool => $c->type === ChangeType::ExceptionHandlingRemoved,
        );

        if ($removedHandling !== []) {
            $risk = $this->escalate($risk, Risk::Medium);
            $signals[] = 'exception_handling_removed';
        }

        // Blast radius
        $blastRadius = $this->calculateBlastRadius($filePaths, $structuralChanges);

        if (count($blastRadius) >= 10) {
            $risk = $this->escalate($risk, Risk::High);
            $signals[] = 'wide_blast_radius:'.count($blastRadius).'_files';
        } elseif (count($blastRadius) >= 5) {
            $risk = $this->escalate($risk, Risk::Medium);
            $signals[] = 'moderate_blast_radius:'.count($blastRadius).'_files';
        }

        return ['risk' => $risk, 'blast_radius' => $blastRadius, 'signals' => $signals];
    }

    /**
     * Find files that depend on changed code but aren't in the PR.
     *
     * Uses regex to find `use` statements and `new` instantiations.
     * Pass a callback that searches repo contents for a pattern.
     *
     * @param  string[]  $filePaths
     * @param  StructuralChange[]  $structuralChanges
     * @return string[]
     */
    private function calculateBlastRadius(array $filePaths, array $structuralChanges): array
    {
        // Extract class names from changed files
        $changedClasses = [];

        foreach ($structuralChanges as $change) {
            if (str_contains($change->symbol, '::')) {
                $changedClasses[] = explode('::', $change->symbol)[0];
            } elseif ($change->type->affectsPublicApi()) {
                $changedClasses[] = $change->symbol;
            }
        }

        // Also derive class names from file paths (PSR-4 convention)
        foreach ($filePaths as $path) {
            if (str_ends_with($path, '.php') && ! $this->isTestFile($path)) {
                $changedClasses[] = pathinfo($path, PATHINFO_FILENAME);
            }
        }

        $changedClasses = array_unique(array_filter($changedClasses));

        // For now, blast radius is the list of class names that changed.
        // The consuming code (Review::analyze()) will use GitHub file search
        // to find actual callers across the repo.
        return $changedClasses;
    }

    /**
     * @param  string[]  $filePaths
     * @return string[]
     */
    private function findCriticalPathMatches(array $filePaths): array
    {
        $matches = [];

        foreach ($filePaths as $path) {
            foreach ($this->criticalPaths as $pattern) {
                if (fnmatch($pattern, $path)) {
                    $matches[] = $path;
                    break;
                }
            }
        }

        return $matches;
    }

    private function escalate(Risk $current, Risk $minimum): Risk
    {
        return $minimum->weight() > $current->weight() ? $minimum : $current;
    }

    private function isCodeFile(string $path): bool
    {
        return str_ends_with($path, '.php');
    }

    private function isTestFile(string $path): bool
    {
        $lower = strtolower($path);

        return str_contains($lower, 'test') || str_contains($lower, 'spec');
    }
}
