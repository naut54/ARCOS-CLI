<?php

declare(strict_types=1);

namespace Arcos\Cli\Commands\Inspect;

use Arcos\Cli\Command;
use Arcos\Cli\IO\Output;

class InspectMiddleware extends Command
{
    public static function signature(): string
    {
        return 'inspect:middleware';
    }

    public static function description(): string
    {
        return 'Show the middleware chain for a route or all routes';
    }

    public function handle(array $args, array $flags): int
    {
        if (!$this->requireProjectContext()) {
            return 1;
        }

        $projectRoot = getcwd();
        $bridge      = new RuntimeBridge();
        $result      = $bridge->run('routes', $projectRoot);

        if (!$result['ok']) {
            Output::error('Could not load routes: ' . $result['error']);
            return 1;
        }

        $routes = $result['data'];

        if (empty($routes)) {
            Output::line();
            Output::comment('  No routes registered.');
            return 0;
        }

        // Optional filter: arcos inspect:middleware "GET /products"
        $filter = $args[0] ?? null;

        if ($filter !== null) {
            $routes = $this->filterRoutes($routes, $filter);

            if (empty($routes)) {
                Output::error("No route matched: \"{$filter}\"");
                Output::comment('  Format: "METHOD /uri"  e.g. "GET /products"');
                return 1;
            }
        }

        foreach ($routes as $i => $route) {
            $this->renderChain($route);

            // Blank line between routes, not after the last one
            if ($i < count($routes) - 1) {
                Output::line();
            }
        }

        Output::line();
        Output::comment('  Execution order: outermost → innermost → controller');

        return 0;
    }

    // Filtering

    /**
     * Filter routes by "METHOD /uri" string.
     *
     * @param  array<int, array<string, mixed>> $routes
     * @return array<int, array<string, mixed>>
     */
    private function filterRoutes(array $routes, string $filter): array
    {
        $parts  = explode(' ', trim($filter), 2);
        $method = strtoupper($parts[0] ?? '');
        $uri    = $parts[1] ?? '';

        return array_values(array_filter(
            $routes,
            fn($r) => $r['method'] === $method && $r['uri'] === $uri
        ));
    }

    // Rendering

    /**
     * @param array<string, mixed> $route
     */
    private function renderChain(array $route): void
    {
        Output::header("Middleware chain — {$route['method']} {$route['uri']}");
        Output::line();

        $middleware = $route['middleware'] ?? [];

        if (empty($middleware)) {
            Output::comment('  No middleware on this route.');
            return;
        }

        // Column widths
        $classWidth = max(5, ...array_map(fn($m) => strlen($m['class']), $middleware));
        $layerWidth = 16; // "subdomain_group" is the longest layer name

        $header = sprintf(
            "  %-{$layerWidth}s  %-{$classWidth}s  %s",
            'LAYER',
            'CLASS',
            'FLAGS'
        );

        $divider = sprintf(
            "  %s  %s  %s",
            str_repeat('─', $layerWidth),
            str_repeat('─', $classWidth),
            str_repeat('─', 20)
        );

        Output::line($header);
        Output::line($divider);

        foreach ($middleware as $entry) {
            $flags = $this->buildFlags($entry);
            $layer = $this->formatLayer($entry['layer']);

            printf(
                "  %-{$layerWidth}s  %-{$classWidth}s  %s\n",
                $layer,
                $entry['class'],
                $flags ?: '—'
            );
        }
    }

    private function buildFlags(array $entry): string
    {
        $flags = [];

        if ($entry['isMandatory']) {
            $flags[] = 'mandatory';
        }

        if ($entry['isSkippable']) {
            $flags[] = 'skippable';
        }

        if ($entry['name'] !== null) {
            $flags[] = "name:{$entry['name']}";
        }

        return implode(', ', $flags);
    }

    private function formatLayer(string $layer): string
    {
        return match ($layer) {
            'global'          => 'Global (always)',
            'subdomain_group' => 'Subdomain group',
            'per_route'       => 'Per-route',
            default           => $layer,
        };
    }
}