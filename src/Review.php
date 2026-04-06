<?php

namespace TheShit\Review;

final class Review
{
    private ?string $conventions = null;

    /** @var array<int, array<string, mixed>> */
    private array $patterns = [];

    /** @var string[] */
    private array $dependents = [];

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

    public function withoutJudgment(): self
    {
        $this->includeJudgment = false;

        return $this;
    }

    public function analyze(): ReviewResult
    {
        // Pipeline implementation in issues #2-10
        throw new \RuntimeException('Review pipeline not yet implemented. See issues #2-10.');
    }
}
