<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Unit;

use Arcos\Cli\Commands\Inspect\InspectRoutes;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Exercises the rendering logic directly via reflection against canned route
 * data, rather than going through the real RuntimeBridge subprocess — that
 * full-flow path is covered by the Support-tier end-to-end test instead.
 */
class InspectRoutesTest extends TestCase
{
    private function renderTable(array $routes): string
    {
        $command    = new InspectRoutes();
        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('renderTable');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($command, $routes);

        return ob_get_clean();
    }

    public function test_marks_auto_resolved_routes(): void
    {
        $output = $this->renderTable([
            ['method' => 'GET', 'uri' => '/products', 'controller' => 'App\\Controllers\\ProductsController', 'action' => 'index', 'source' => 'auto'],
        ]);

        $this->assertStringContainsString('[auto]', $output);
    }

    public function test_explicit_routes_show_a_dash_instead_of_auto(): void
    {
        $output = $this->renderTable([
            ['method' => 'GET', 'uri' => '/products', 'controller' => 'App\\Controllers\\ProductsController', 'action' => 'index', 'source' => 'explicit'],
        ]);

        $this->assertStringNotContainsString('[auto]', $output);
        $this->assertStringContainsString('—', $output);
    }

    public function test_renders_method_uri_controller_and_action(): void
    {
        $output = $this->renderTable([
            ['method' => 'POST', 'uri' => '/products', 'controller' => 'App\\Controllers\\ProductsController', 'action' => 'store', 'source' => 'explicit'],
        ]);

        $this->assertStringContainsString('POST', $output);
        $this->assertStringContainsString('/products', $output);
        $this->assertStringContainsString('App\\Controllers\\ProductsController', $output);
        $this->assertStringContainsString('store', $output);
    }
}
