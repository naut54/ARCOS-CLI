<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Support;

use Arcos\Cli\Commands\Inspect\InspectRoutes;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real inspect:routes command (Unit-tier rendering logic + the real
 * RuntimeBridge subprocess) against the checked-in fixture project.
 */
class InspectRoutesEndToEndTest extends TestCase
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

    public function test_lists_all_fixture_routes_grouped_by_subdomain(): void
    {
        $command = new InspectRoutes();

        ob_start();
        $exitCode = $command->handle([], []);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('subdomain: api', $output);
        $this->assertStringContainsString('/products', $output);
        $this->assertStringContainsString('/products/show', $output);
        $this->assertStringContainsString('ProductsController', $output);
        $this->assertStringContainsString('3 routes registered across 1 subdomain', $output);
    }
}
