<?php

namespace TheShit\Review\Analyzers;

use ConduitUi\GitHubConnector\Connector;
use TheShit\Review\Data\StructuralChange;
use TheShit\Review\Enums\Severity;
use TheShit\Review\Finding;
use TheShit\Review\Requests\GetFileContents;
use TheShit\Review\Requests\SearchCode;

final class DependentsChecker
{
    public function __construct(
        private readonly Connector $connector,
    ) {}

    /**
     * Check if public API changes in this PR would break dependent repos.
     *
     * @param  string  $sourceRepo  The repo being reviewed (owner/repo)
     * @param  StructuralChange[]  $structuralChanges  Changes detected in the PR
     * @param  string[]  $dependents  Repos to check (owner/repo format)
     * @return Finding[]
     */
    public function check(string $sourceRepo, array $structuralChanges, array $dependents): array
    {
        if ($dependents === []) {
            return [];
        }

        $apiChanges = array_filter(
            $structuralChanges,
            fn (StructuralChange $c): bool => $c->affectsPublicApi(),
        );

        if ($apiChanges === []) {
            return [];
        }

        $findings = [];

        foreach ($dependents as $dependent) {
            if (! $this->dependsOn($dependent, $sourceRepo)) {
                continue;
            }

            foreach ($apiChanges as $change) {
                $finding = $this->checkSymbolUsage($dependent, $change);

                if ($finding !== null) {
                    $findings[] = $finding;
                }
            }
        }

        return $findings;
    }

    private function checkSymbolUsage(string $dependent, StructuralChange $change): ?Finding
    {
        $symbol = $this->extractClassName($change->symbol);

        if ($symbol === '') {
            return null;
        }

        $usages = $this->searchForUsages($dependent, $symbol);

        if ($usages === []) {
            return null;
        }

        $fileList = implode(', ', array_slice($usages, 0, 3));
        $overflow = count($usages) > 3 ? ' and '.(count($usages) - 3).' more' : '';

        return new Finding(
            severity: Severity::Risk,
            confidence: 0.85,
            file: $change->file,
            lines: [],
            message: "Breaking change: `{$change->symbol}` ({$change->type->value}) is used in {$dependent} — {$fileList}{$overflow}",
        );
    }

    /**
     * Check if a dependent repo requires the source package.
     */
    private function dependsOn(string $dependent, string $sourceRepo): bool
    {
        $composerJson = $this->fetchFile($dependent, 'composer.json');

        if ($composerJson === null) {
            return false;
        }

        $composer = json_decode($composerJson, true);

        if (! is_array($composer)) {
            return false;
        }

        $allDeps = array_keys(array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? [],
        ));

        // Match by exact package name or by repo name suffix
        $sourceSlug = explode('/', $sourceRepo)[1] ?? '';

        foreach ($allDeps as $dep) {
            if ($dep === $sourceRepo || str_ends_with($dep, "/{$sourceSlug}")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Search a repo for usages of a symbol via GitHub code search.
     *
     * @return string[] File paths where the symbol is used
     */
    private function searchForUsages(string $repo, string $symbol): array
    {
        try {
            $response = $this->connector->send(new SearchCode($symbol, $repo));

            if (! $response->successful()) {
                return [];
            }

            $items = $response->json('items') ?? [];

            return array_map(
                fn (array $item): string => $item['path'] ?? 'unknown',
                array_slice($items, 0, 10),
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchFile(string $repo, string $path): ?string
    {
        try {
            [$owner, $repoName] = explode('/', $repo, 2);

            $response = $this->connector->send(new GetFileContents($owner, $repoName, $path));

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if (($data['encoding'] ?? '') === 'base64') {
                return base64_decode($data['content']);
            }

            return $data['content'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractClassName(string $symbol): string
    {
        if (str_contains($symbol, '::')) {
            return explode('::', $symbol)[0];
        }

        return $symbol;
    }
}
