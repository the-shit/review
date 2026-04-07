<?php

namespace TheShit\Review;

use ConduitUI\Pr\DataTransferObjects\File;
use ConduitUI\Pr\PullRequests;
use TheShit\Review\Analyzers\Classifier;
use TheShit\Review\Analyzers\ConventionChecker;
use TheShit\Review\Analyzers\JudgmentCall;
use TheShit\Review\Analyzers\RiskAssessor;
use TheShit\Review\Contracts\Rule;
use TheShit\Review\Enums\Verdict;

final class Review
{
    private ?string $conventions = null;

    /** @var array<int, array<string, mixed>> */
    private array $patterns = [];

    /** @var string[] */
    private array $dependents = [];

    /** @var Rule[] */
    private array $rules = [];

    private bool $includeJudgment = true;

    private function __construct(
        private readonly string $repo,
        private readonly int $prNumber,
    ) {}

    public static function for(string $repo, int $prNumber): self
    {
        return new self($repo, $prNumber);
    }

    public function withConventions(string $conventions): self
    {
        $this->conventions = $conventions;

        return $this;
    }

    /**
     * @param  array<int, array<string, mixed>>  $patterns
     */
    public function withPatterns(array $patterns): self
    {
        $this->patterns = $patterns;

        return $this;
    }

    /**
     * @param  string[]  $dependents
     */
    public function withDependents(array $dependents): self
    {
        $this->dependents = $dependents;

        return $this;
    }

    /**
     * @param  Rule[]  $rules
     */
    public function withRules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function withoutJudgment(): self
    {
        $this->includeJudgment = false;

        return $this;
    }

    public function analyze(): ReviewResult
    {
        // 1. Fetch PR data via conduit-ui/pr
        $pr = PullRequests::find($this->repo, $this->prNumber);
        $files = $pr->files();
        $filePaths = array_map(fn (File $f): string => $f->filename, $files);

        // 2. Classify
        $classification = (new Classifier)->classify(
            $pr->data->title,
            $pr->data->body ?? '',
            $filePaths,
        );

        // 3. Risk assessment
        $totalAdditions = 0;
        $totalDeletions = 0;

        foreach ($files as $file) {
            $totalAdditions += $file->additions;
            $totalDeletions += $file->deletions;
        }

        $criticalPaths = config("review.repos.{$this->repo}.critical_paths")
            ?? config('review.critical_paths', []);

        $riskAssessor = new RiskAssessor($criticalPaths);
        $riskResult = $riskAssessor->assess(
            $filePaths,
            [], // structural changes require file-at-ref support (#10 follow-up)
            $classification,
            $totalAdditions,
            $totalDeletions,
        );

        // 4. Convention checking
        $findings = [];

        if ($this->rules !== []) {
            $patches = $this->extractPatches($files);
            $findings = (new ConventionChecker($this->rules))->checkAll($patches);
        }

        // 5. Judgment call (optional)
        if ($this->includeJudgment) {
            $patches = $this->extractPatches($files);
            $llmFindings = (new JudgmentCall)->call(
                $classification,
                $riskResult['risk'],
                [],
                $findings,
                $patches,
                $this->conventions,
            );

            $findings = [...$findings, ...$llmFindings];
        }

        // 6. Build result
        return new ReviewResult(
            classification: $classification,
            risk: $riskResult['risk'],
            blastRadius: $riskResult['blast_radius'],
            findings: collect($findings),
            summary: $this->buildSummary($classification, $riskResult, $findings),
            repo: $this->repo,
            prNumber: $this->prNumber,
        );
    }

    /**
     * Analyze and post the review to GitHub.
     */
    public function reviewAndPost(): ReviewResult
    {
        $result = $this->analyze();
        $pr = PullRequests::find($this->repo, $this->prNumber);

        $verdict = $result->verdict();
        $body = $result->summary;

        if ($result->findings->isNotEmpty()) {
            $body .= "\n\n### Findings\n";

            foreach ($result->findings as $finding) {
                if ($finding->aboveThreshold()) {
                    $location = $finding->file !== '' ? " (`{$finding->file}`)" : '';
                    $body .= "- **{$finding->severity->value}**{$location}: {$finding->message}\n";
                }
            }
        }

        match ($verdict) {
            Verdict::Approve => $pr->approve($body)->submit(),
            Verdict::RequestChanges => $pr->requestChanges($body)->submit(),
            Verdict::Comment => $pr->review()->comment($body)->submit(),
        };

        return $result;
    }

    /**
     * @param  File[]  $files
     * @return array<string, string>
     */
    private function extractPatches(array $files): array
    {
        $patches = [];

        foreach ($files as $file) {
            if ($file->patch !== null) {
                $patches[$file->filename] = $file->patch;
            }
        }

        return $patches;
    }

    /**
     * @param  array{risk: Enums\Risk, blast_radius: string[], signals: string[]}  $riskResult
     * @param  Finding[]  $findings
     */
    private function buildSummary(Enums\Classification $classification, array $riskResult, array $findings): string
    {
        $parts = [];
        $parts[] = "**{$classification->value}** PR — risk: **{$riskResult['risk']->value}**";

        if ($riskResult['signals'] !== []) {
            $parts[] = 'Signals: '.implode(', ', array_slice($riskResult['signals'], 0, 5));
        }

        $bugCount = count(array_filter($findings, fn (Finding $f): bool => $f->severity === Enums\Severity::Bug && $f->aboveThreshold()));
        $conventionCount = count(array_filter($findings, fn (Finding $f): bool => $f->severity === Enums\Severity::Convention && $f->aboveThreshold()));

        if ($bugCount > 0) {
            $parts[] = "{$bugCount} bug(s) found";
        }

        if ($conventionCount > 0) {
            $parts[] = "{$conventionCount} convention violation(s)";
        }

        if ($bugCount === 0 && $conventionCount === 0) {
            $parts[] = 'No issues found';
        }

        return implode('. ', $parts).'.';
    }
}
