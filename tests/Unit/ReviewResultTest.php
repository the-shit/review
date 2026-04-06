<?php

use TheShit\Review\Enums\Classification;
use TheShit\Review\Enums\Risk;
use TheShit\Review\Enums\Severity;
use TheShit\Review\Enums\Verdict;
use TheShit\Review\Finding;
use TheShit\Review\ReviewResult;

function makeResult(array $findings = []): ReviewResult
{
    return new ReviewResult(
        classification: Classification::BugFix,
        risk: Risk::Medium,
        blastRadius: [],
        findings: collect($findings),
        summary: 'Test review',
        repo: 'test/repo',
        prNumber: 1,
    );
}

it('approves when no findings', function (): void {
    $result = makeResult();

    expect($result)
        ->verdict()->toBe(Verdict::Approve)
        ->and($result->classification)->toBe(Classification::BugFix)
        ->and($result->findings)->toBeEmpty();
});

it('requests changes on bug findings above threshold', function (): void {
    $result = makeResult([
        new Finding(Severity::Bug, 0.95, 'app/Foo.php', [10], 'Null dereference'),
    ]);

    expect($result->verdict())->toBe(Verdict::RequestChanges);
});

it('comments on non-blocking findings', function (): void {
    $result = makeResult([
        new Finding(Severity::Suggestion, 0.8, 'app/Foo.php', [10], 'Consider extract method'),
    ]);

    expect($result->verdict())->toBe(Verdict::Comment);
});

it('requests changes on 3+ convention violations', function (): void {
    $result = makeResult([
        new Finding(Severity::Convention, 0.9, 'app/A.php', [1], 'Missing return type'),
        new Finding(Severity::Convention, 0.9, 'app/B.php', [1], 'No constructor promotion'),
        new Finding(Severity::Convention, 0.9, 'app/C.php', [1], 'Using DB:: facade'),
    ]);

    expect($result)
        ->verdict()->toBe(Verdict::RequestChanges)
        ->and($result->findings)->toHaveCount(3);
});

it('ignores findings below confidence threshold', function (): void {
    $result = makeResult([
        new Finding(Severity::Bug, 0.3, 'app/Foo.php', [10], 'Maybe a bug?'),
    ]);

    expect($result->verdict())->toBe(Verdict::Approve);
});
