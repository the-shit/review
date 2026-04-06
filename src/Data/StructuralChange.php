<?php

namespace TheShit\Review\Data;

use TheShit\Review\Enums\ChangeType;

final readonly class StructuralChange
{
    public function __construct(
        public string $file,
        public ChangeType $type,
        public string $symbol,
        public ?string $before = null,
        public ?string $after = null,
    ) {}

    public function affectsPublicApi(): bool
    {
        return $this->type->affectsPublicApi();
    }
}
