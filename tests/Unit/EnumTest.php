<?php

use TheShit\Review\Enums\Classification;
use TheShit\Review\Enums\Risk;
use TheShit\Review\Enums\Severity;
use TheShit\Review\Enums\Verdict;

it('has all classification types', function (): void {
    expect(Classification::cases())
        ->toHaveCount(6)
        ->and(Classification::BugFix->value)->toBe('bug_fix')
        ->and(Classification::Feature->value)->toBe('feature')
        ->and(Classification::Refactor->value)->toBe('refactor')
        ->and(Classification::Docs->value)->toBe('docs')
        ->and(Classification::Dependency->value)->toBe('dependency')
        ->and(Classification::Chore->value)->toBe('chore');
});

it('scores risk weight correctly', function (): void {
    expect(Risk::Low->weight())->toBe(1)
        ->and(Risk::Medium->weight())->toBe(2)
        ->and(Risk::High->weight())->toBe(3)
        ->and(Risk::Critical->weight())->toBe(4);
});

it('compares risk thresholds', function (): void {
    expect(Risk::High->meetsThreshold(Risk::Medium))->toBeTrue()
        ->and(Risk::Low->meetsThreshold(Risk::High))->toBeFalse()
        ->and(Risk::Medium->meetsThreshold(Risk::Medium))->toBeTrue();
});

it('identifies blocking severities', function (): void {
    expect(Severity::Bug->blocksApproval())->toBeTrue()
        ->and(Severity::Convention->blocksApproval())->toBeFalse()
        ->and(Severity::Risk->blocksApproval())->toBeFalse()
        ->and(Severity::Suggestion->blocksApproval())->toBeFalse();
});

it('has all verdict types', function (): void {
    expect(Verdict::cases())
        ->toHaveCount(3)
        ->and(Verdict::Approve->value)->toBe('approve')
        ->and(Verdict::RequestChanges->value)->toBe('request_changes')
        ->and(Verdict::Comment->value)->toBe('comment');
});
