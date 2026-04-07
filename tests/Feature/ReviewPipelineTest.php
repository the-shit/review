<?php

use ConduitUi\GitHubConnector\Connector;
use ConduitUI\Pr\PullRequests;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use TheShit\Review\Analyzers\JudgmentAgent;
use TheShit\Review\Enums\Classification;
use TheShit\Review\Enums\Verdict;
use TheShit\Review\Review;
use TheShit\Review\Rules\ForbiddenPattern;

function mockPrResponse(string $title = 'fix: correct Postgres JSON syntax', ?string $body = null): array
{
    return [
        'number' => 117,
        'title' => $title,
        'body' => $body,
        'state' => 'open',
        'user' => ['id' => 1, 'login' => 'jordanpartridge', 'avatar_url' => '', 'html_url' => '', 'type' => 'User'],
        'html_url' => 'https://github.com/test/repo/pull/117',
        'created_at' => '2026-04-06T10:00:00Z',
        'updated_at' => '2026-04-06T10:00:00Z',
        'closed_at' => null,
        'merged_at' => null,
        'merge_commit_sha' => null,
        'draft' => false,
        'additions' => 16,
        'deletions' => 7,
        'changed_files' => 2,
        'assignee' => null,
        'assignees' => [],
        'requested_reviewers' => [],
        'labels' => [],
        'head' => [
            'ref' => 'fix/watchdog',
            'sha' => 'abc123',
            'user' => ['id' => 1, 'login' => 'jordanpartridge', 'avatar_url' => '', 'html_url' => '', 'type' => 'User'],
            'repo' => ['id' => 1, 'name' => 'repo', 'full_name' => 'test/repo', 'html_url' => '', 'private' => false],
        ],
        'base' => [
            'ref' => 'main',
            'sha' => 'def456',
            'user' => ['id' => 1, 'login' => 'jordanpartridge', 'avatar_url' => '', 'html_url' => '', 'type' => 'User'],
            'repo' => ['id' => 1, 'name' => 'repo', 'full_name' => 'test/repo', 'html_url' => '', 'private' => false],
        ],
    ];
}

function mockFilesResponse(): array
{
    return [
        [
            'sha' => 'aaa',
            'filename' => 'app/Console/Commands/Watchdog.php',
            'status' => 'modified',
            'additions' => 10,
            'deletions' => 5,
            'changes' => 15,
            'blob_url' => '',
            'raw_url' => '',
            'contents_url' => '',
            'patch' => "@@ -60,4 +60,6 @@\n-                ->whereRaw(\"JSON_EXTRACT(data, '$.completedAt') IS NULL\")\n+                ->whereRaw(\"(data->>'completedAt') IS NULL\")",
        ],
        [
            'sha' => 'bbb',
            'filename' => 'app/Console/Commands/Reflect.php',
            'status' => 'modified',
            'additions' => 6,
            'deletions' => 2,
            'changes' => 8,
            'blob_url' => '',
            'raw_url' => '',
            'contents_url' => '',
            'patch' => "@@ -46,3 +46,5 @@\n-        \$channelId = \$this->openDm(\$token, \$jordanUserId);\n+        \$channelId = config('services.lexi.channel') ?: \$this->openDm(\$token, \$jordanUserId);",
        ],
    ];
}

function setupMockGitHub(): void
{
    $mockClient = new MockClient([
        '*' => MockResponse::make(mockPrResponse()),
    ]);

    $connector = new Connector('fake-token');
    $connector->withMockClient($mockClient);

    PullRequests::setConnector($connector);
}

it('runs the full pipeline without judgment', function (): void {
    $mockClient = new MockClient([
        '*pulls/117' => MockResponse::make(mockPrResponse()),
        '*pulls/117/files' => MockResponse::make(mockFilesResponse()),
    ]);

    $connector = new Connector('fake-token');
    $connector->withMockClient($mockClient);
    PullRequests::setConnector($connector);

    $result = Review::for('test/repo', 117)
        ->withoutJudgment()
        ->analyze();

    expect($result)
        ->classification->toBe(Classification::BugFix)
        ->repo->toBe('test/repo')
        ->prNumber->toBe(117)
        ->and($result->findings)->toBeEmpty()
        ->and($result->verdict())->toBe(Verdict::Approve)
        ->and($result->summary)->toContain('bug_fix');
});

it('catches convention violations in the pipeline', function (): void {
    $mockClient = new MockClient([
        '*pulls/117' => MockResponse::make(mockPrResponse('feat: add new service')),
        '*pulls/117/files' => MockResponse::make([
            [
                'sha' => 'ccc',
                'filename' => 'app/Services/NewService.php',
                'status' => 'added',
                'additions' => 20,
                'deletions' => 0,
                'changes' => 20,
                'blob_url' => '',
                'raw_url' => '',
                'contents_url' => '',
                'patch' => "@@ -0,0 +1,5 @@\n+<?php\n+class NewService {\n+    public function handle() {\n+        \$users = DB::table('users')->get();\n+    }\n+}",
            ],
        ]),
    ]);

    $connector = new Connector('fake-token');
    $connector->withMockClient($mockClient);
    PullRequests::setConnector($connector);

    $result = Review::for('test/repo', 117)
        ->withoutJudgment()
        ->withRules([
            new ForbiddenPattern('DB::', 'Use Eloquent, not DB:: facade'),
        ])
        ->analyze();

    expect($result->findings)->toHaveCount(1)
        ->and($result->findings->first()->message)->toBe('Use Eloquent, not DB:: facade')
        ->and($result->classification)->toBe(Classification::Feature);
});

it('includes judgment call findings when enabled', function (): void {
    $mockClient = new MockClient([
        '*pulls/117' => MockResponse::make(mockPrResponse()),
        '*pulls/117/files' => MockResponse::make(mockFilesResponse()),
    ]);

    $connector = new Connector('fake-token');
    $connector->withMockClient($mockClient);
    PullRequests::setConnector($connector);

    JudgmentAgent::fake([
        '[{"severity":"bug","file":"app/Console/Commands/Watchdog.php","lines":[62],"message":"Missing index on verb_snapshots for JSON query","confidence":0.75}]',
    ]);

    $result = Review::for('test/repo', 117)
        ->analyze();

    expect($result->findings)->toHaveCount(1)
        ->and($result->findings->first()->source)->toBe('llm')
        ->and($result->findings->first()->confidence)->toBe(0.75);
});

it('generates a useful summary', function (): void {
    $mockClient = new MockClient([
        '*pulls/117' => MockResponse::make(mockPrResponse()),
        '*pulls/117/files' => MockResponse::make(mockFilesResponse()),
    ]);

    $connector = new Connector('fake-token');
    $connector->withMockClient($mockClient);
    PullRequests::setConnector($connector);

    JudgmentAgent::fake(['[]']);

    $result = Review::for('test/repo', 117)->analyze();

    expect($result->summary)
        ->toContain('bug_fix')
        ->toContain('No issues found');
});
