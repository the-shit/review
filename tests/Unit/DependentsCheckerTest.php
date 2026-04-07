<?php

use ConduitUi\GitHubConnector\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use TheShit\Review\Analyzers\DependentsChecker;
use TheShit\Review\Data\StructuralChange;
use TheShit\Review\Enums\ChangeType;
use TheShit\Review\Enums\Severity;
use TheShit\Review\Requests\GetFileContents;
use TheShit\Review\Requests\SearchCode;

function makeCheckerConnector(array $responses): Connector
{
    $connector = new Connector('fake-token');
    $connector->withMockClient(new MockClient($responses));

    return $connector;
}

it('skips when no dependents provided', function (): void {
    $connector = makeCheckerConnector([]);
    $checker = new DependentsChecker($connector);

    $findings = $checker->check('the-shit/health', [], []);

    expect($findings)->toBeEmpty();
});

it('skips when no public API changes', function (): void {
    $connector = makeCheckerConnector([]);
    $checker = new DependentsChecker($connector);

    $changes = [
        new StructuralChange('src/Foo.php', ChangeType::ExceptionHandlingAdded, 'try/catch'),
        new StructuralChange('src/Bar.php', ChangeType::MethodAdded, 'Bar::newMethod'),
    ];

    $findings = $checker->check('the-shit/health', $changes, ['jordanpartridge/lexi-agent']);

    expect($findings)->toBeEmpty();
});

it('detects breaking change when dependent uses the symbol', function (): void {
    $connector = makeCheckerConnector([
        GetFileContents::class => MockResponse::make([
            'content' => base64_encode(json_encode(['require' => ['the-shit/health' => '^1.0']])),
            'encoding' => 'base64',
        ]),
        SearchCode::class => MockResponse::make([
            'items' => [
                ['path' => 'app/Services/HealthService.php'],
                ['path' => 'app/Jobs/ProcessHealth.php'],
            ],
        ]),
    ]);

    $checker = new DependentsChecker($connector);

    $changes = [
        new StructuralChange('src/HealthClient.php', ChangeType::MethodRemoved, 'HealthClient::getData'),
    ];

    $findings = $checker->check('the-shit/health', $changes, ['jordanpartridge/lexi-agent']);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->severity)->toBe(Severity::Risk)
        ->and($findings[0]->message)->toContain('HealthClient::getData')
        ->and($findings[0]->message)->toContain('jordanpartridge/lexi-agent')
        ->and($findings[0]->message)->toContain('HealthService.php');
});

it('skips dependent that does not use the source package', function (): void {
    $connector = makeCheckerConnector([
        GetFileContents::class => MockResponse::make([
            'content' => base64_encode(json_encode(['require' => ['laravel/framework' => '^13.0']])),
            'encoding' => 'base64',
        ]),
    ]);

    $checker = new DependentsChecker($connector);

    $changes = [
        new StructuralChange('src/Client.php', ChangeType::SignatureChanged, 'Client::fetch', 'string $url', 'string $url, int $timeout'),
    ];

    $findings = $checker->check('the-shit/health', $changes, ['org/some-repo']);

    expect($findings)->toBeEmpty();
});

it('handles missing composer.json gracefully', function (): void {
    $connector = makeCheckerConnector([
        GetFileContents::class => MockResponse::make([], 404),
    ]);

    $checker = new DependentsChecker($connector);

    $changes = [
        new StructuralChange('src/Foo.php', ChangeType::MethodRemoved, 'Foo::bar'),
    ];

    $findings = $checker->check('the-shit/review', $changes, ['org/no-composer']);

    expect($findings)->toBeEmpty();
});

it('handles search API failure gracefully', function (): void {
    $connector = makeCheckerConnector([
        GetFileContents::class => MockResponse::make([
            'content' => base64_encode(json_encode(['require' => ['the-shit/review' => '^1.0']])),
            'encoding' => 'base64',
        ]),
        SearchCode::class => MockResponse::make([], 403),
    ]);

    $checker = new DependentsChecker($connector);

    $changes = [
        new StructuralChange('src/Review.php', ChangeType::SignatureChanged, 'Review::analyze', '', 'ReviewResult'),
    ];

    $findings = $checker->check('the-shit/review', $changes, ['jordanpartridge/lexi-agent']);

    expect($findings)->toBeEmpty();
});

it('checks multiple dependents', function (): void {
    $callCount = 0;
    $connector = makeCheckerConnector([
        GetFileContents::class => MockResponse::make([
            'content' => base64_encode(json_encode(['require' => ['the-shit/health' => '^1.0']])),
            'encoding' => 'base64',
        ]),
        SearchCode::class => MockResponse::make([
            'items' => [['path' => 'app/Client.php']],
        ]),
    ]);

    $checker = new DependentsChecker($connector);

    $changes = [
        new StructuralChange('src/Api.php', ChangeType::MethodRemoved, 'Api::v1'),
    ];

    $findings = $checker->check('the-shit/health', $changes, [
        'jordanpartridge/lexi-agent',
        'jordanpartridge/mindkeeper',
    ]);

    expect($findings)->toHaveCount(2);
});
