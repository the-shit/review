<?php

use TheShit\Review\Analyzers\Classifier;
use TheShit\Review\Enums\Classification;

it('classifies conventional commit prefixes', function (string $title, Classification $expected): void {
    $classifier = new Classifier;

    expect($classifier->classify($title, '', []))->toBe($expected);
})->with([
    ['fix: correct Postgres JSON syntax', Classification::BugFix],
    ['fix(watchdog): replace JSON_EXTRACT', Classification::BugFix],
    ['feat: add Checks API', Classification::Feature],
    ['feat(github): file contents at ref', Classification::Feature],
    ['refactor: extract BaseAgent', Classification::Refactor],
    ['docs: rewrite README', Classification::Docs],
    ['chore: update CI workflow', Classification::Chore],
    ['ci: add pest + pint checks', Classification::Chore],
    ['style: fix formatting', Classification::Chore],
    ['test: add classifier tests', Classification::Chore],
    ['perf: optimize query', Classification::Refactor],
]);

it('classifies from title keywords when no conventional prefix', function (): void {
    $classifier = new Classifier;

    expect($classifier->classify('Fix broken Watchdog query', '', []))->toBe(Classification::BugFix)
        ->and($classifier->classify('Add new health tool', '', []))->toBe(Classification::Feature)
        ->and($classifier->classify('Refactor memory pipeline', '', []))->toBe(Classification::Refactor)
        ->and($classifier->classify('Bump laravel/ai to 0.3', '', []))->toBe(Classification::Dependency)
        ->and($classifier->classify('Update README with new examples', '', []))->toBe(Classification::Docs);
});

it('classifies from file paths when title is ambiguous', function (): void {
    $classifier = new Classifier;

    expect($classifier->classify('Update things', '', ['README.md', 'CHANGELOG.md']))->toBe(Classification::Docs)
        ->and($classifier->classify('Update things', '', ['composer.lock']))->toBe(Classification::Dependency)
        ->and($classifier->classify('Update things', '', ['.github/workflows/ci.yml']))->toBe(Classification::Chore);
});

it('defaults to feature when ambiguous', function (): void {
    $classifier = new Classifier;

    expect($classifier->classify('Do something cool', '', ['app/Services/Foo.php']))->toBe(Classification::Feature);
});
