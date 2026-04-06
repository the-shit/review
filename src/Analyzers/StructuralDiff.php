<?php

namespace TheShit\Review\Analyzers;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\NodeFinder;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use TheShit\Review\Data\StructuralChange;
use TheShit\Review\Enums\ChangeType;

final class StructuralDiff
{
    private Parser $parser;

    private NodeFinder $finder;

    private Standard $printer;

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->finder = new NodeFinder;
        $this->printer = new Standard;
    }

    /**
     * Compare two PHP source strings and return structural changes.
     *
     * @return StructuralChange[]
     */
    public function compare(string $file, string $before, string $after): array
    {
        $beforeAst = $this->parse($before);
        $afterAst = $this->parse($after);

        if ($beforeAst === null || $afterAst === null) {
            return [];
        }

        return [
            ...$this->compareClasses($file, $beforeAst, $afterAst),
            ...$this->compareMethods($file, $beforeAst, $afterAst),
            ...$this->compareInterfaces($file, $beforeAst, $afterAst),
            ...$this->compareExceptionHandling($file, $beforeAst, $afterAst),
        ];
    }

    /**
     * Analyze a newly added file.
     *
     * @return StructuralChange[]
     */
    public function analyzeAdded(string $file, string $source): array
    {
        $ast = $this->parse($source);

        if ($ast === null) {
            return [];
        }

        $changes = [];

        foreach ($this->finder->findInstanceOf($ast, Class_::class) as $class) {
            $changes[] = new StructuralChange(
                file: $file,
                type: ChangeType::ClassAdded,
                symbol: $this->className($class),
            );
        }

        return $changes;
    }

    /**
     * @return Node\Stmt[]|null
     */
    private function parse(string $source): ?array
    {
        try {
            return $this->parser->parse($source);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  Node\Stmt[]  $beforeAst
     * @param  Node\Stmt[]  $afterAst
     * @return StructuralChange[]
     */
    private function compareClasses(string $file, array $beforeAst, array $afterAst): array
    {
        $changes = [];
        $beforeClasses = $this->indexByName($this->finder->findInstanceOf($beforeAst, Class_::class));
        $afterClasses = $this->indexByName($this->finder->findInstanceOf($afterAst, Class_::class));

        foreach (array_diff_key($beforeClasses, $afterClasses) as $name => $_) {
            $changes[] = new StructuralChange($file, ChangeType::ClassRemoved, $name);
        }

        foreach (array_diff_key($afterClasses, $beforeClasses) as $name => $_) {
            $changes[] = new StructuralChange($file, ChangeType::ClassAdded, $name);
        }

        return $changes;
    }

    /**
     * @param  Node\Stmt[]  $beforeAst
     * @param  Node\Stmt[]  $afterAst
     * @return StructuralChange[]
     */
    private function compareMethods(string $file, array $beforeAst, array $afterAst): array
    {
        $changes = [];
        $beforeMethods = $this->indexMethods($beforeAst);
        $afterMethods = $this->indexMethods($afterAst);

        foreach (array_diff_key($beforeMethods, $afterMethods) as $key => $_) {
            $changes[] = new StructuralChange($file, ChangeType::MethodRemoved, $key);
        }

        foreach (array_diff_key($afterMethods, $beforeMethods) as $key => $_) {
            $changes[] = new StructuralChange($file, ChangeType::MethodAdded, $key);
        }

        foreach (array_intersect_key($beforeMethods, $afterMethods) as $key => $beforeMethod) {
            $afterMethod = $afterMethods[$key];
            $changes = [...$changes, ...$this->compareMethod($file, $key, $beforeMethod, $afterMethod)];
        }

        return $changes;
    }

    /**
     * @return StructuralChange[]
     */
    private function compareMethod(string $file, string $symbol, ClassMethod $before, ClassMethod $after): array
    {
        $changes = [];

        // Visibility change
        if ($this->visibility($before) !== $this->visibility($after)) {
            $changes[] = new StructuralChange(
                $file, ChangeType::VisibilityChanged, $symbol,
                $this->visibility($before), $this->visibility($after),
            );
        }

        // Return type change
        $beforeReturn = $before->returnType ? $this->printer->prettyPrint([$before->returnType]) : null;
        $afterReturn = $after->returnType ? $this->printer->prettyPrint([$after->returnType]) : null;

        if ($beforeReturn !== $afterReturn) {
            $changes[] = new StructuralChange(
                $file, ChangeType::ReturnTypeChanged, $symbol,
                $beforeReturn, $afterReturn,
            );
        }

        // Parameter signature change
        $beforeParams = $this->paramSignature($before);
        $afterParams = $this->paramSignature($after);

        if ($beforeParams !== $afterParams) {
            $changes[] = new StructuralChange(
                $file, ChangeType::SignatureChanged, $symbol,
                $beforeParams, $afterParams,
            );
        }

        // Constructor specifically
        if ($before->name->toString() === '__construct' && $beforeParams !== $afterParams) {
            $changes[] = new StructuralChange(
                $file, ChangeType::ConstructorChanged, $symbol,
                $beforeParams, $afterParams,
            );
        }

        return $changes;
    }

    /**
     * @param  Node\Stmt[]  $beforeAst
     * @param  Node\Stmt[]  $afterAst
     * @return StructuralChange[]
     */
    private function compareInterfaces(string $file, array $beforeAst, array $afterAst): array
    {
        $changes = [];
        $beforeInterfaces = $this->indexByName($this->finder->findInstanceOf($beforeAst, Interface_::class));
        $afterInterfaces = $this->indexByName($this->finder->findInstanceOf($afterAst, Interface_::class));

        foreach (array_keys(array_diff_key($beforeInterfaces, $afterInterfaces)) as $name) {
            $changes[] = new StructuralChange($file, ChangeType::InterfaceChanged, $name, 'removed');
        }

        foreach (array_intersect_key($beforeInterfaces, $afterInterfaces) as $name => $beforeInterface) {
            $afterInterface = $afterInterfaces[$name];
            $beforeMethodCount = count($beforeInterface->getMethods());
            $afterMethodCount = count($afterInterface->getMethods());

            if ($beforeMethodCount !== $afterMethodCount) {
                $changes[] = new StructuralChange(
                    $file, ChangeType::InterfaceChanged, $name,
                    "{$beforeMethodCount} methods", "{$afterMethodCount} methods",
                );
            }
        }

        return $changes;
    }

    /**
     * @param  Node\Stmt[]  $beforeAst
     * @param  Node\Stmt[]  $afterAst
     * @return StructuralChange[]
     */
    private function compareExceptionHandling(string $file, array $beforeAst, array $afterAst): array
    {
        $changes = [];
        $beforeCount = count($this->finder->findInstanceOf($beforeAst, Node\Stmt\TryCatch::class));
        $afterCount = count($this->finder->findInstanceOf($afterAst, Node\Stmt\TryCatch::class));

        if ($afterCount > $beforeCount) {
            $changes[] = new StructuralChange(
                $file, ChangeType::ExceptionHandlingAdded, 'try/catch',
                (string) $beforeCount, (string) $afterCount,
            );
        } elseif ($afterCount < $beforeCount) {
            $changes[] = new StructuralChange(
                $file, ChangeType::ExceptionHandlingRemoved, 'try/catch',
                (string) $beforeCount, (string) $afterCount,
            );
        }

        return $changes;
    }

    private function visibility(ClassMethod $method): string
    {
        if ($method->isPublic()) {
            return 'public';
        }

        if ($method->isProtected()) {
            return 'protected';
        }

        return 'private';
    }

    private function paramSignature(ClassMethod $method): string
    {
        $params = [];

        foreach ($method->params as $param) {
            $type = $param->type ? $this->printer->prettyPrint([$param->type]) : 'mixed';
            $name = '$'.$param->var->name;
            $params[] = "{$type} {$name}";
        }

        return implode(', ', $params);
    }

    /**
     * @param  Node[]  $nodes
     * @return array<string, Class_|Interface_>
     */
    private function indexByName(array $nodes): array
    {
        $indexed = [];

        foreach ($nodes as $node) {
            $name = $this->className($node);

            if ($name !== '') {
                $indexed[$name] = $node;
            }
        }

        return $indexed;
    }

    private function className(Class_|Interface_ $node): string
    {
        return $node->name?->toString() ?? '';
    }

    /**
     * @param  Node\Stmt[]  $ast
     * @return array<string, ClassMethod>
     */
    private function indexMethods(array $ast): array
    {
        $methods = [];

        foreach ($this->finder->findInstanceOf($ast, ClassMethod::class) as $method) {
            $class = $method->getAttribute('parent');
            $className = $class instanceof Class_ || $class instanceof Interface_
                ? ($class->name?->toString() ?? '')
                : '';

            $key = $className !== '' ? "{$className}::{$method->name}" : $method->name->toString();
            $methods[$key] = $method;
        }

        return $methods;
    }
}
