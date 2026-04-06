<?php

use TheShit\Review\Review;

it('registers the review config', function (): void {
    expect(config('review'))->toBeArray();
    expect(config('review.github'))->toBeArray();
    expect(config('review.models.default'))->toBe('x-ai/grok-4.1-fast');
    expect(config('review.thresholds.confidence'))->toBe(0.7);
});

it('has auto-approve disabled by default', function (): void {
    expect(config('review.auto_approve.enabled'))->toBeFalse();
    expect(config('review.auto_approve.require_ci_pass'))->toBeTrue();
});

it('defaults to squash merge strategy', function (): void {
    expect(config('review.merge_strategy'))->toBe('squash');
});

it('builds a review via static factory', function (): void {
    $review = Review::for('jordanpartridge/lexi-agent', 118);

    expect($review)->toBeInstanceOf(Review::class);
});

it('accepts fluent configuration', function (): void {
    $review = Review::for('jordanpartridge/lexi-agent', 118)
        ->withConventions('# CLAUDE.md rules here')
        ->withPatterns([['title' => 'Use Eloquent', 'content' => 'Avoid DB::']])
        ->withDependents(['the-shit/health'])
        ->withoutJudgment();

    expect($review)->toBeInstanceOf(Review::class);
});
