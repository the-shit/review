<?php

namespace TheShit\Review;

use Illuminate\Support\Collection;
use TheShit\Review\Enums\Classification;
use TheShit\Review\Enums\Risk;
use TheShit\Review\Enums\Severity;
use TheShit\Review\Enums\Verdict;

final readonly class ReviewResult
{
    /**
     * @param  Collection<int, Finding>  $findings
     * @param  string[]  $blastRadius
     */
    public function __construct(
        public Classification $classification,
        public Risk $risk,
        public array $blastRadius,
        public Collection $findings,
        public string $summary,
        public string $repo,
        public int $prNumber,
    ) {}

    public function verdict(float $confidenceThreshold = 0.7): Verdict
    {
        $threshold = $confidenceThreshold;

        $actionable = $this->findings->filter(
            fn (Finding $f): bool => $f->aboveThreshold($threshold),
        );

        if ($actionable->contains(fn (Finding $f): bool => $f->severity->blocksApproval())) {
            return Verdict::RequestChanges;
        }

        $conventionCount = $actionable->where('severity', Severity::Convention)->count();

        if ($conventionCount >= 3) {
            return Verdict::RequestChanges;
        }

        if ($actionable->isNotEmpty()) {
            return Verdict::Comment;
        }

        return Verdict::Approve;
    }
}
