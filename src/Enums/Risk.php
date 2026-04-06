<?php

namespace TheShit\Review\Enums;

enum Risk: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function meetsThreshold(self $threshold): bool
    {
        return $this->weight() >= $threshold->weight();
    }

    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }
}
