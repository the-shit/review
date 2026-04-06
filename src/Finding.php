<?php

namespace TheShit\Review;

use TheShit\Review\Enums\Severity;

final readonly class Finding
{
    /**
     * @param  int[]  $lines
     */
    public function __construct(
        public Severity $severity,
        public float $confidence,
        public string $file,
        public array $lines,
        public string $message,
        public string $source = 'static',
    ) {}

    public function aboveThreshold(float $threshold = 0.7): bool
    {
        return $this->confidence >= $threshold;
    }
}
