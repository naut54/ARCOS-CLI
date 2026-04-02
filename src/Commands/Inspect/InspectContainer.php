<?php

declare(strict_types=1);

namespace Arcos\Cli\Commands\Inspect;

use Arcos\Cli\Command;
use Arcos\Cli\IO\Output;

class InspectContainer extends Command
{
    public static function signature(): string
    {
        return 'inspect:container';
    }

    public static function description(): string
    {
        return 'List all container bindings declared in index.php';
    }

    public function handle(array $args, array $flags): int
    {
        if (!$this->requireProjectContext()) {
            return 1;
        }

        $indexPath = $this->projectPath('index.php');

        if (!file_exists($indexPath)) {
            Output::error('index.php not found.');
            return 1;
        }

        $content  = file_get_contents($indexPath);
        $bindings = $this->parseBindings($content);

        Output::header('Container bindings');
        Output::line();

        if (empty($bindings)) {
            Output::comment('  No bindings found in index.php.');
            return 0;
        }

        $this->renderTable($bindings);

        Output::line();
        $count = count($bindings);
        $noun  = $count === 1 ? 'binding' : 'bindings';
        Output::comment("  {$count} {$noun} declared.");

        return 0;
    }

    // Parsing

    /**
     * Statically parse index.php for $container->singleton() and $container->bind() calls.
     *
     * Matches patterns like:
     *   $container->singleton(Foo::class, fn($c) => new Foo());
     *   $container->bind(Bar::class, fn($c) => new Bar($c->make(Baz::class)));
     *
     * @return array<int, array{type: string, class: string, factory: string}>
     */
    private function parseBindings(string $content): array
    {
        $bindings = [];

        // Match singleton() and bind() calls
        // Captures: type, abstract class, and the full factory expression
        $pattern = '/\$container\s*->\s*(singleton|bind)\s*\(\s*([A-Za-z0-9_\\\\]+)::class\s*,\s*(fn\s*\([^)]*\)\s*=>[^\n;]+)/';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $type    = $match[1]; // 'singleton' or 'bind'
            $class   = $match[2]; // e.g. 'InventoryService' or fully qualified
            $factory = trim($match[3]); // the fn() => ... expression

            // Resolve short class name to fully qualified if a use statement exists
            $fqcn    = $this->resolveClass($class, $content);
            $factory = $this->summarizeFactory($factory);

            $bindings[] = [
                'type'    => $type,
                'class'   => $fqcn,
                'factory' => $factory,
            ];
        }

        return $bindings;
    }

    /**
     * Try to resolve a short class name to its FQCN using use statements in index.php.
     */
    private function resolveClass(string $class, string $content): string
    {
        // Already fully qualified (contains backslash)
        if (str_contains($class, '\\')) {
            return $class;
        }

        // Look for: use Some\Namespace\ClassName;
        $pattern = '/^use\s+([A-Za-z0-9_\\\\]+\\\\' . preg_quote($class, '/') . ')\s*;/m';

        if (preg_match($pattern, $content, $match)) {
            return $match[1];
        }

        return $class;
    }

    /**
     * Shorten a factory expression for display.
     *
     * fn($c) => new InventoryService()
     *   → new InventoryService()
     *
     * fn($c) => new ProductsController($c->make(InventoryService::class))
     *   → new ProductsController(...)
     */
    private function summarizeFactory(string $factory): string
    {
        // Strip the fn($c) => prefix
        $factory = preg_replace('/^fn\s*\([^)]*\)\s*=>\s*/', '', $factory);
        $factory = trim($factory);

        // Strip trailing semicolon from the regex capture bleeding over
        $factory = rtrim($factory, ';');

        // If it has $c->make() calls inside, summarize with (...)
        if (str_contains($factory, '$c->make(')) {
            $factory = preg_replace('/\(.*$/s', '(...)', $factory);
            return $factory;
        }

        // Remove the extra closing paren from the outer $container->singleton(..., fn => new X())
        // The regex captures up to and including the factory expression — trim last )
        if (str_ends_with($factory, ')')) {
            $factory = substr($factory, 0, -1);
        }

        return $factory;
    }

    // Rendering

    /**
     * @param array<int, array{type: string, class: string, factory: string}> $bindings
     */
    private function renderTable(array $bindings): void
    {
        $typeWidth    = max(4, ...array_map(fn($b) => strlen($b['type']), $bindings));
        $classWidth   = max(5, ...array_map(fn($b) => strlen($b['class']), $bindings));
        $factoryWidth = max(7, ...array_map(fn($b) => strlen($b['factory']), $bindings));

        $header = sprintf(
            "  %-{$typeWidth}s  %-{$classWidth}s  %s",
            'TYPE',
            'CLASS',
            'FACTORY'
        );

        $divider = sprintf(
            "  %s  %s  %s",
            str_repeat('─', $typeWidth),
            str_repeat('─', $classWidth),
            str_repeat('─', $factoryWidth)
        );

        Output::line($header);
        Output::line($divider);

        foreach ($bindings as $binding) {
            printf(
                "  %-{$typeWidth}s  %-{$classWidth}s  %s\n",
                $binding['type'],
                $binding['class'],
                $binding['factory']
            );
        }
    }
}