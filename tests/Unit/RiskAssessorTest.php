<?php

use TheShit\Review\Analyzers\RiskAssessor;
use TheShit\Review\Data\StructuralChange;
use TheShit\Review\Enums\ChangeType;
use TheShit\Review\Enums\Classification;
use TheShit\Review\Enums\Risk;

it('scores docs PRs as low risk', function (): void {
    $assessor = new RiskAssessor;

    $result = $assessor->assess(['README.md'], [], Classification::Docs);

    expect($result['risk'])->toBe(Risk::Low)
        ->and($result['signals'])->toContain('classification_low_risk');
});

it('scores dependency PRs as low risk', function (): void {
    $assessor = new RiskAssessor;

    $result = $assessor->assess(['composer.lock'], [], Classification::Dependency);

    expect($result['risk'])->toBe(Risk::Low);
});

it('escalates risk for critical paths', function (): void {
    $assessor = new RiskAssessor;

    $result = $assessor->assess(
        ['app/Providers/AuthServiceProvider.php'],
        [],
        Classification::Feature,
    );

    expect($result['risk']->weight())->toBeGreaterThanOrEqual(Risk::Medium->weight())
        ->and(collect($result['signals'])->contains(fn ($s) => str_starts_with($s, 'critical_paths:')))->toBeTrue();
});

it('escalates risk for large PRs', function (): void {
    $assessor = new RiskAssessor;

    $files = array_map(fn ($i) => "app/File{$i}.php", range(1, 25));
    $result = $assessor->assess($files, [], Classification::Feature);

    expect($result['risk']->weight())->toBeGreaterThanOrEqual(Risk::High->weight())
        ->and(collect($result['signals'])->contains(fn ($s) => str_starts_with($s, 'large_pr:')))->toBeTrue();
});

it('escalates risk for high churn', function (): void {
    $assessor = new RiskAssessor;

    $result = $assessor->assess(
        ['app/BigFile.php'],
        [],
        Classification::Feature,
        totalAdditions: 400,
        totalDeletions: 200,
    );

    expect($result['risk']->weight())->toBeGreaterThanOrEqual(Risk::High->weight())
        ->and(collect($result['signals'])->contains(fn ($s) => str_starts_with($s, 'high_churn:')))->toBeTrue();
});

it('escalates risk for public API changes', function (): void {
    $assessor = new RiskAssessor;

    $changes = [
        new StructuralChange('app/Service.php', ChangeType::SignatureChanged, 'Service::handle', 'string $x', 'string $x, int $y'),
    ];

    $result = $assessor->assess(['app/Service.php'], $changes, Classification::Feature);

    expect($result['risk']->weight())->toBeGreaterThanOrEqual(Risk::Medium->weight())
        ->and(collect($result['signals'])->contains(fn ($s) => str_starts_with($s, 'api_surface_changes:')))->toBeTrue();
});

it('signals missing test changes', function (): void {
    $assessor = new RiskAssessor;

    $result = $assessor->assess(
        ['app/Service.php', 'app/Controller.php'],
        [],
        Classification::Feature,
    );

    expect($result['signals'])->toContain('no_test_changes');
});

it('does not signal missing tests when tests are included', function (): void {
    $assessor = new RiskAssessor;

    $result = $assessor->assess(
        ['app/Service.php', 'tests/Feature/ServiceTest.php'],
        [],
        Classification::Feature,
    );

    expect($result['signals'])->not->toContain('no_test_changes');
});

it('signals exception handling removal', function (): void {
    $assessor = new RiskAssessor;

    $changes = [
        new StructuralChange('app/Foo.php', ChangeType::ExceptionHandlingRemoved, 'try/catch', '2', '1'),
    ];

    $result = $assessor->assess(['app/Foo.php'], $changes, Classification::Refactor);

    expect(collect($result['signals'])->contains('exception_handling_removed'))->toBeTrue();
});

it('accepts custom critical paths', function (): void {
    $assessor = new RiskAssessor(['app/Agent/*', 'app/Jobs/*']);

    $result = $assessor->assess(
        ['app/Agent/LexiAgent.php'],
        [],
        Classification::Feature,
    );

    expect(collect($result['signals'])->contains(fn ($s) => str_starts_with($s, 'critical_paths:')))->toBeTrue();
});

it('keeps low risk for small safe PRs', function (): void {
    $assessor = new RiskAssessor;

    $result = $assessor->assess(
        ['app/Tools/NewTool.php', 'tests/Feature/NewToolTest.php'],
        [],
        Classification::Feature,
        totalAdditions: 50,
        totalDeletions: 0,
    );

    expect($result['risk'])->toBe(Risk::Low);
});
