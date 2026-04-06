<?php

use TheShit\Review\Enums\Severity;
use TheShit\Review\Finding;

it('reports above threshold when confidence is high', function (): void {
    $finding = new Finding(
        severity: Severity::Bug,
        confidence: 0.95,
        file: 'app/Watchdog.php',
        lines: [62, 63],
        message: 'MySQL syntax on Postgres',
    );

    expect($finding)
        ->aboveThreshold(0.7)->toBeTrue()
        ->and($finding->source)->toBe('static');
});

it('reports below threshold when confidence is low', function (): void {
    $finding = new Finding(
        severity: Severity::Suggestion,
        confidence: 0.5,
        file: 'app/Watchdog.php',
        lines: [10],
        message: 'Consider early return',
    );

    expect($finding->aboveThreshold(0.7))->toBeFalse();
});

it('accepts llm source override', function (): void {
    $finding = new Finding(
        severity: Severity::Risk,
        confidence: 0.75,
        file: 'app/Bar.php',
        lines: [5, 10],
        message: 'Potential null dereference',
        source: 'llm',
    );

    expect($finding)
        ->source->toBe('llm')
        ->severity->toBe(Severity::Risk)
        ->confidence->toBe(0.75)
        ->file->toBe('app/Bar.php')
        ->lines->toBe([5, 10]);
});
