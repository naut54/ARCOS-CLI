<?php

declare(strict_types=1);

namespace Arcos\Cli\Commands\Inspect;

use Arcos\Cli\Command;
use Arcos\Cli\IO\Output;

class InspectRoutes extends Command
{
    public static function signature(): string
    {
        return 'inspect:routes';
    }

    public static function description(): string
    {
        return 'List all registered routes';
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

        // Group routes by subdomain
        $bySubdomain = [];
        foreach ($routes as $route) {
            $bySubdomain[$route['subdomain']][] = $route;
        }

        $total = count($routes);

        foreach ($bySubdomain as $subdomain => $subRoutes) {
            Output::header("Routes — subdomain: {$subdomain}");
            Output::line();
            $this->renderTable($subRoutes);
            Output::line();
        }

        $subdomainCount = count($bySubdomain);
        $noun           = $subdomainCount === 1 ? 'subdomain' : 'subdomains';
        Output::comment("  {$total} routes registered across {$subdomainCount} {$noun}.");

        return 0;
    }

    // Rendering

    /**
     * @param array<int, array<string, mixed>> $routes
     */
    private function renderTable(array $routes): void
    {
        // Calculate column widths dynamically
        $methodWidth     = 6;  // "METHOD"
        $uriWidth        = max(3, ...array_map(fn($r) => strlen($r['uri']), $routes));
        $controllerWidth = max(10, ...array_map(fn($r) => strlen($r['controller']), $routes));
        $actionWidth     = max(6, ...array_map(fn($r) => strlen($r['action']), $routes));

        $header = sprintf(
            "  %-{$methodWidth}s  %-{$uriWidth}s  %-{$controllerWidth}s  %-{$actionWidth}s  %s",
            'METHOD',
            'URI',
            'CONTROLLER',
            'ACTION',
            'SOURCE'
        );

        $divider = sprintf(
            "  %s  %s  %s  %s  %s",
            str_repeat('─', $methodWidth),
            str_repeat('─', $uriWidth),
            str_repeat('─', $controllerWidth),
            str_repeat('─', $actionWidth),
            str_repeat('─', 8)
        );

        Output::line($header);
        Output::line($divider);

        foreach ($routes as $route) {
            $source = $route['source'] === 'auto' ? '[auto]' : '—';

            printf(
                "  %-{$methodWidth}s  %-{$uriWidth}s  %-{$controllerWidth}s  %-{$actionWidth}s  %s\n",
                $route['method'],
                $route['uri'],
                $route['controller'],
                $route['action'],
                $source
            );
        }
    }
}