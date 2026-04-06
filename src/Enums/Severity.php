<?php

namespace TheShit\Review\Enums;

enum Severity: string
{
    case Bug = 'bug';
    case Convention = 'convention';
    case Risk = 'risk';
    case Suggestion = 'suggestion';

    public function blocksApproval(): bool
    {
        return match ($this) {
            self::Bug => true,
            self::Convention, self::Risk, self::Suggestion => false,
        };
    }
}
