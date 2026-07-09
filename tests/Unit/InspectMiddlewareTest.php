<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Unit;

use Arcos\Cli\Commands\Inspect\InspectMiddleware;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Exercises the filtering/rendering logic directly via reflection against
 * canned route+middleware data, rather than going through the real
 * RuntimeBridge subprocess — that full-flow path is covered by the
 * Support-tier end-to-end test instead.
 */
class InspectMiddlewareTest extends TestCase
{
    private function method(string $name): ReflectionMethod
    {
        $method = (new ReflectionClass(InspectMiddleware::class))->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    public function test_filter_routes_matches_method_and_uri(): void
    {
        $routes = [
            ['method' => 'GET', 'uri' => '/products'],
            ['method' => 'POST', 'uri' => '/products'],
        ];

        $result = $this->method('filterRoutes')->invoke(new InspectMiddleware(), $routes, 'GET /products');

        $this->assertCount(1, $result);
        $this->assertSame('GET', $result[0]['method']);
    }

    public function test_filter_routes_returns_empty_for_no_match(): void
    {
        $routes = [['method' => 'GET', 'uri' => '/products']];

        $result = $this->method('filterRoutes')->invoke(new InspectMiddleware(), $routes, 'DELETE /nope');

        $this->assertSame([], $result);
    }

    public function test_build_flags_includes_mandatory_skippable_and_name(): void
    {
        $flags = $this->method('buildFlags')->invoke(new InspectMiddleware(), [
            'isMandatory' => true,
            'isSkippable' => false,
            'name'        => 'audit',
        ]);

        $this->assertStringContainsString('mandatory', $flags);
        $this->assertStringContainsString('name:audit', $flags);
        $this->assertStringNotContainsString('skippable', $flags);
    }

    public function test_build_flags_is_empty_when_nothing_is_set(): void
    {
        $flags = $this->method('buildFlags')->invoke(new InspectMiddleware(), [
            'isMandatory' => false,
            'isSkippable' => false,
            'name'        => null,
        ]);

        $this->assertSame('', $flags);
    }

    public function test_format_layer_maps_known_layers_to_labels(): void
    {
        $formatLayer = $this->method('formatLayer');
        $instance    = new InspectMiddleware();

        $this->assertSame('Global (always)', $formatLayer->invoke($instance, 'global'));
        $this->assertSame('Subdomain group', $formatLayer->invoke($instance, 'subdomain_group'));
        $this->assertSame('Per-route', $formatLayer->invoke($instance, 'per_route'));
        $this->assertSame('something_else', $formatLayer->invoke($instance, 'something_else'));
    }

    public function test_render_chain_shows_no_middleware_message_when_empty(): void
    {
        $renderChain = $this->method('renderChain');

        ob_start();
        $renderChain->invoke(new InspectMiddleware(), ['method' => 'GET', 'uri' => '/health', 'middleware' => []]);
        $output = ob_get_clean();

        $this->assertStringContainsString('No middleware on this route', $output);
    }

    public function test_render_chain_lists_middleware_with_flags(): void
    {
        $renderChain = $this->method('renderChain');

        ob_start();
        $renderChain->invoke(new InspectMiddleware(), [
            'method'     => 'GET',
            'uri'        => '/products',
            'middleware' => [
                ['class' => 'App\\Middleware\\LoggerMiddleware', 'isMandatory' => true, 'isSkippable' => false, 'name' => null, 'layer' => 'global'],
            ],
        ]);
        $output = ob_get_clean();

        $this->assertStringContainsString('App\\Middleware\\LoggerMiddleware', $output);
        $this->assertStringContainsString('mandatory', $output);
        $this->assertStringContainsString('Global (always)', $output);
    }
}
