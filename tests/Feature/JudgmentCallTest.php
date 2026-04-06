<?php

use TheShit\Review\Analyzers\JudgmentAgent;
use TheShit\Review\Analyzers\JudgmentCall;
use TheShit\Review\Data\StructuralChange;
use TheShit\Review\Enums\ChangeType;
use TheShit\Review\Enums\Classification;
use TheShit\Review\Enums\Risk;
use TheShit\Review\Enums\Severity;
use TheShit\Review\Finding;

it('parses LLM findings with capped confidence', function (): void {
    JudgmentAgent::fake([
        '[{"severity":"bug","file":"app/Foo.php","lines":[42],"message":"Null dereference on optional param","confidence":0.95}]',
    ]);

    $call = new JudgmentCall;
    $findings = $call->call(
        classification: Classification::BugFix,
        risk: Risk::Medium,
        structuralChanges: [],
        existingFindings: [],
        patches: ['app/Foo.php' => "@@ -1,3 +1,3 @@\n-old\n+new"],
    );

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->severity)->toBe(Severity::Bug)
        ->and($findings[0]->confidence)->toBe(0.8) // capped at 0.8
        ->and($findings[0]->source)->toBe('llm')
        ->and($findings[0]->message)->toBe('Null dereference on optional param');
});

it('returns empty findings for clean PR', function (): void {
    JudgmentAgent::fake(['[]']);

    $call = new JudgmentCall;
    $findings = $call->call(
        classification: Classification::Feature,
        risk: Risk::Low,
        structuralChanges: [],
        existingFindings: [],
        patches: ['app/Bar.php' => "@@ -1,3 +1,3 @@\n+clean code"],
    );

    expect($findings)->toBeEmpty();
});

it('handles malformed LLM response gracefully', function (): void {
    JudgmentAgent::fake(['This is not JSON at all, sorry!']);

    $call = new JudgmentCall;
    $findings = $call->call(
        classification: Classification::Feature,
        risk: Risk::Low,
        structuralChanges: [],
        existingFindings: [],
        patches: [],
    );

    expect($findings)->toBeEmpty();
});

it('handles LLM response with code fences', function (): void {
    JudgmentAgent::fake([
        "```json\n[{\"severity\":\"risk\",\"file\":\"app/X.php\",\"lines\":[],\"message\":\"Race condition\",\"confidence\":0.7}]\n```",
    ]);

    $call = new JudgmentCall;
    $findings = $call->call(
        classification: Classification::Feature,
        risk: Risk::Medium,
        structuralChanges: [],
        existingFindings: [],
        patches: ['app/X.php' => "@@ -1 +1 @@\n+code"],
    );

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->severity)->toBe(Severity::Risk)
        ->and($findings[0]->source)->toBe('llm');
});

it('skips findings with invalid severity', function (): void {
    JudgmentAgent::fake([
        '[{"severity":"nitpick","file":"app/Y.php","lines":[],"message":"Use early return","confidence":0.5}]',
    ]);

    $call = new JudgmentCall;
    $findings = $call->call(
        classification: Classification::Refactor,
        risk: Risk::Low,
        structuralChanges: [],
        existingFindings: [],
        patches: [],
    );

    expect($findings)->toBeEmpty();
});

it('includes structural changes and existing findings in prompt context', function (): void {
    JudgmentAgent::fake(['[]']);

    $call = new JudgmentCall;
    $findings = $call->call(
        classification: Classification::BugFix,
        risk: Risk::High,
        structuralChanges: [
            new StructuralChange('app/Svc.php', ChangeType::SignatureChanged, 'Svc::handle', 'string $x', 'string $x, int $y'),
        ],
        existingFindings: [
            new Finding(Severity::Convention, 0.9, 'app/Svc.php', [10], 'Missing return type'),
        ],
        patches: ['app/Svc.php' => "@@ -1 +1 @@\n+changed"],
        conventions: '# CLAUDE.md: Use Eloquent not DB::',
    );

    expect($findings)->toBeEmpty();
});

it('returns empty on agent exception', function (): void {
    JudgmentAgent::fake(fn () => throw new RuntimeException('API down'));

    $call = new JudgmentCall;
    $findings = $call->call(
        classification: Classification::Feature,
        risk: Risk::Low,
        structuralChanges: [],
        existingFindings: [],
        patches: [],
    );

    expect($findings)->toBeEmpty();
});
