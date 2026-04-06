<?php

use TheShit\Review\Analyzers\StructuralDiff;
use TheShit\Review\Enums\ChangeType;

it('detects method signature changes', function (): void {
    $diff = new StructuralDiff;

    $before = <<<'PHP'
    <?php
    class Foo {
        public function bar(string $name): void {}
    }
    PHP;

    $after = <<<'PHP'
    <?php
    class Foo {
        public function bar(string $name, int $age): void {}
    }
    PHP;

    $changes = $diff->compare('app/Foo.php', $before, $after);

    expect($changes)->not->toBeEmpty()
        ->and(collect($changes)->contains(fn ($c) => $c->type === ChangeType::SignatureChanged))->toBeTrue();
});

it('detects added methods', function (): void {
    $diff = new StructuralDiff;

    $before = <<<'PHP'
    <?php
    class Foo {
        public function bar(): void {}
    }
    PHP;

    $after = <<<'PHP'
    <?php
    class Foo {
        public function bar(): void {}
        public function baz(): string { return ''; }
    }
    PHP;

    $changes = $diff->compare('app/Foo.php', $before, $after);

    expect(collect($changes)->contains(fn ($c) => $c->type === ChangeType::MethodAdded))->toBeTrue();
});

it('detects removed methods', function (): void {
    $diff = new StructuralDiff;

    $before = <<<'PHP'
    <?php
    class Foo {
        public function bar(): void {}
        public function baz(): void {}
    }
    PHP;

    $after = <<<'PHP'
    <?php
    class Foo {
        public function bar(): void {}
    }
    PHP;

    $changes = $diff->compare('app/Foo.php', $before, $after);

    expect(collect($changes)->contains(fn ($c) => $c->type === ChangeType::MethodRemoved))->toBeTrue();
});

it('detects visibility changes', function (): void {
    $diff = new StructuralDiff;

    $before = <<<'PHP'
    <?php
    class Foo {
        public function bar(): void {}
    }
    PHP;

    $after = <<<'PHP'
    <?php
    class Foo {
        private function bar(): void {}
    }
    PHP;

    $changes = $diff->compare('app/Foo.php', $before, $after);

    expect(collect($changes)->contains(
        fn ($c) => $c->type === ChangeType::VisibilityChanged
            && $c->before === 'public'
            && $c->after === 'private',
    ))->toBeTrue();
});

it('detects return type changes', function (): void {
    $diff = new StructuralDiff;

    $before = <<<'PHP'
    <?php
    class Foo {
        public function bar(): string { return ''; }
    }
    PHP;

    $after = <<<'PHP'
    <?php
    class Foo {
        public function bar(): ?string { return null; }
    }
    PHP;

    $changes = $diff->compare('app/Foo.php', $before, $after);

    expect(collect($changes)->contains(fn ($c) => $c->type === ChangeType::ReturnTypeChanged))->toBeTrue();
});

it('detects constructor changes', function (): void {
    $diff = new StructuralDiff;

    $before = <<<'PHP'
    <?php
    class Foo {
        public function __construct(private string $name) {}
    }
    PHP;

    $after = <<<'PHP'
    <?php
    class Foo {
        public function __construct(private string $name, private int $age) {}
    }
    PHP;

    $changes = $diff->compare('app/Foo.php', $before, $after);

    expect(collect($changes)->contains(fn ($c) => $c->type === ChangeType::ConstructorChanged))->toBeTrue();
});

it('detects added exception handling', function (): void {
    $diff = new StructuralDiff;

    $before = <<<'PHP'
    <?php
    class Foo {
        public function bar(): void {
            doSomething();
        }
    }
    PHP;

    $after = <<<'PHP'
    <?php
    class Foo {
        public function bar(): void {
            try {
                doSomething();
            } catch (\Throwable $e) {
                log($e);
            }
        }
    }
    PHP;

    $changes = $diff->compare('app/Foo.php', $before, $after);

    expect(collect($changes)->contains(fn ($c) => $c->type === ChangeType::ExceptionHandlingAdded))->toBeTrue();
});

it('detects new classes in added files', function (): void {
    $diff = new StructuralDiff;

    $source = <<<'PHP'
    <?php
    class NewService {
        public function handle(): void {}
    }
    PHP;

    $changes = $diff->analyzeAdded('app/NewService.php', $source);

    expect($changes)->toHaveCount(1)
        ->and($changes[0]->type)->toBe(ChangeType::ClassAdded)
        ->and($changes[0]->symbol)->toBe('NewService');
});

it('returns empty for unparseable PHP', function (): void {
    $diff = new StructuralDiff;

    $changes = $diff->compare('app/Broken.php', '<?php this is not valid', '<?php also broken');

    expect($changes)->toBeEmpty();
});

it('marks public API affecting changes correctly', function (): void {
    $diff = new StructuralDiff;

    $before = <<<'PHP'
    <?php
    class Foo {
        public function bar(string $x): void {}
    }
    PHP;

    $after = <<<'PHP'
    <?php
    class Foo {
        protected function bar(string $x, int $y): ?string { return null; }
    }
    PHP;

    $changes = $diff->compare('app/Foo.php', $before, $after);
    $apiChanges = array_filter($changes, fn ($c) => $c->affectsPublicApi());

    expect($apiChanges)->not->toBeEmpty();
});
