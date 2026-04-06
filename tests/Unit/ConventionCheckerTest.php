<?php

use TheShit\Review\Analyzers\ConventionChecker;
use TheShit\Review\Enums\Severity;
use TheShit\Review\Rules\ForbiddenPattern;
use TheShit\Review\Rules\NamingConvention;
use TheShit\Review\Rules\RequiredPattern;

function makePatch(string $added): string
{
    $lines = explode("\n", $added);
    $patch = '@@ -1,0 +1,'.count($lines)." @@\n";
    foreach ($lines as $line) {
        $patch .= "+{$line}\n";
    }

    return $patch;
}

// --- ForbiddenPattern ---

it('catches forbidden literal strings in added lines', function (): void {
    $checker = new ConventionChecker([
        new ForbiddenPattern('DB::', 'Use Eloquent, not DB:: facade'),
    ]);

    $patch = makePatch('$results = DB::table("users")->get();');
    $findings = $checker->check('app/Service.php', $patch);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->severity)->toBe(Severity::Convention)
        ->and($findings[0]->message)->toBe('Use Eloquent, not DB:: facade');
});

it('catches forbidden regex patterns', function (): void {
    $checker = new ConventionChecker([
        new ForbiddenPattern('/env\s*\(/', 'Use config() not env() outside config files', 'app/**'),
    ]);

    $patch = makePatch('$key = env("APP_KEY");');
    $findings = $checker->check('app/Service.php', $patch);

    expect($findings)->toHaveCount(1);
});

it('skips forbidden pattern for non-matching file globs', function (): void {
    $checker = new ConventionChecker([
        new ForbiddenPattern('env(', 'Use config() not env()', 'app/**'),
    ]);

    $patch = makePatch('$key = env("APP_KEY");');
    $findings = $checker->check('config/app.php', $patch);

    expect($findings)->toBeEmpty();
});

it('ignores removed lines', function (): void {
    $checker = new ConventionChecker([
        new ForbiddenPattern('DB::', 'Use Eloquent'),
    ]);

    $patch = "@@ -1,2 +1,1 @@\n-\$old = DB::table('x')->get();\n+\$new = User::query()->get();\n";
    $findings = $checker->check('app/Service.php', $patch);

    expect($findings)->toBeEmpty();
});

it('reports no findings when code is clean', function (): void {
    $checker = new ConventionChecker([
        new ForbiddenPattern('DB::', 'Use Eloquent'),
        new ForbiddenPattern('dd(', 'No debug statements'),
    ]);

    $patch = makePatch('$users = User::query()->where("active", true)->get();');
    $findings = $checker->check('app/Service.php', $patch);

    expect($findings)->toBeEmpty();
});

// --- RequiredPattern ---

it('flags missing required pattern', function (): void {
    $checker = new ConventionChecker([
        new RequiredPattern(
            '/:\s*(string|int|bool|float|array|void|self|static|mixed|\?\w+)/',
            'All methods must have return type declarations',
            'app/**',
            '/function\s+\w+\s*\(/',
        ),
    ]);

    $patch = makePatch('    public function handle($request) {');
    $findings = $checker->check('app/Handler.php', $patch);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->message)->toBe('All methods must have return type declarations');
});

it('passes when required pattern is present', function (): void {
    $checker = new ConventionChecker([
        new RequiredPattern(
            '/:\s*(string|int|bool|float|array|void|self|static|mixed|\?\w+)/',
            'All methods must have return type declarations',
            'app/**',
            '/function\s+\w+\s*\(/',
        ),
    ]);

    $patch = makePatch('    public function handle($request): void {');
    $findings = $checker->check('app/Handler.php', $patch);

    expect($findings)->toBeEmpty();
});

it('skips required pattern when context is not present', function (): void {
    $checker = new ConventionChecker([
        new RequiredPattern(
            '/:\s*void/',
            'Must return void',
            null,
            '/function\s+\w+/',
        ),
    ]);

    $patch = makePatch('$x = 42;');
    $findings = $checker->check('app/Foo.php', $patch);

    expect($findings)->toBeEmpty();
});

// --- NamingConvention ---

it('flags class names that violate naming convention', function (): void {
    $checker = new ConventionChecker([
        new NamingConvention('/^[A-Z][a-zA-Z]+$/', 'Class names must be PascalCase', 'class'),
    ]);

    $patch = makePatch('class my_bad_class {');
    $findings = $checker->check('app/my_bad_class.php', $patch);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->message)->toContain('my_bad_class');
});

it('passes valid class names', function (): void {
    $checker = new ConventionChecker([
        new NamingConvention('/^[A-Z][a-zA-Z]+$/', 'Class names must be PascalCase', 'class'),
    ]);

    $patch = makePatch('class MyService {');
    $findings = $checker->check('app/MyService.php', $patch);

    expect($findings)->toBeEmpty();
});

it('flags method names that violate naming convention', function (): void {
    $checker = new ConventionChecker([
        new NamingConvention('/^[a-z][a-zA-Z]+$/', 'Method names must be camelCase', 'method'),
    ]);

    $patch = makePatch('    public function GetAllUsers(): array {');
    $findings = $checker->check('app/Service.php', $patch);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->message)->toContain('GetAllUsers');
});

// --- Multiple rules ---

it('runs all rules and collects findings', function (): void {
    $checker = new ConventionChecker([
        new ForbiddenPattern('DB::', 'Use Eloquent'),
        new ForbiddenPattern('dd(', 'No debug statements'),
    ]);

    $patch = makePatch("dd(DB::table('users')->get());");
    $findings = $checker->check('app/Service.php', $patch);

    expect($findings)->toHaveCount(2);
});

it('checks multiple files at once', function (): void {
    $checker = new ConventionChecker([
        new ForbiddenPattern('DB::', 'Use Eloquent'),
    ]);

    $findings = $checker->checkAll([
        'app/A.php' => makePatch('DB::table("a")'),
        'app/B.php' => makePatch('User::query()'),
        'app/C.php' => makePatch('DB::select("select 1")'),
    ]);

    expect($findings)->toHaveCount(2)
        ->and($findings[0]->file)->toBe('app/A.php')
        ->and($findings[1]->file)->toBe('app/C.php');
});

it('extracts correct line numbers from real diff patches', function (): void {
    $checker = new ConventionChecker([
        new ForbiddenPattern('DB::', 'Use Eloquent'),
    ]);

    $patch = <<<'DIFF'
    @@ -10,6 +10,8 @@ class Foo
         public function bar(): void
         {
    +        $x = DB::table('users');
    +        $y = User::query()->get();
             return;
         }
     }
    DIFF;

    $findings = $checker->check('app/Foo.php', $patch);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->lines)->toBe([12]);
});
