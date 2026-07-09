<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Support;

use Arcos\Cli\Commands\Inspect\InspectMiddleware;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real inspect:middleware command (Unit-tier rendering logic + the
 * real RuntimeBridge subprocess) against the checked-in fixture project.
 */
class InspectMiddlewareEndToEndTest extends TestCase
{
    private string $originalCwd;

    #[Before]
    public function goToFixtureProject(): void
    {
        $this->originalCwd = getcwd();
        chdir(__DIR__ . '/../Fixtures/sample-project');
    }

    #[After]
    public function restoreCwd(): void
    {
        chdir($this->originalCwd);
    }

    public function test_shows_the_full_three_layer_chain_for_a_specific_route(): void
    {
        $command = new InspectMiddleware();

        ob_start();
        $exitCode = $command->handle(['GET /products/show'], []);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Global (always)', $output);
        $this->assertStringContainsString('App\\Middleware\\LoggerMiddleware', $output);
        $this->assertStringContainsString('mandatory', $output);
        $this->assertStringContainsString('Subdomain group', $output);
        $this->assertStringContainsString('App\\Middleware\\VerbValidationMiddleware', $output);
        $this->assertStringContainsString('Per-route', $output);
        $this->assertStringContainsString('App\\Middleware\\RateLimitMiddleware', $output);
    }

    public function test_shows_every_route_when_no_filter_is_given(): void
    {
        $command = new InspectMiddleware();

        ob_start();
        $exitCode = $command->handle([], []);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('GET /products', $output);
        $this->assertStringContainsString('GET /products/show', $output);
        $this->assertStringContainsString('POST /products', $output);
    }

    public function test_unmatched_filter_returns_1_and_a_clear_error(): void
    {
        $command = new InspectMiddleware();

        ob_start();
        $exitCode = $command->handle(['DELETE /does-not-exist'], []);
        $output = ob_get_clean();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('No route matched', $output);
    }
}
