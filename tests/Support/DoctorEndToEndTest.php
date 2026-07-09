<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Support;

use Arcos\Cli\Commands\Doctor;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real doctor command — including the real RuntimeBridge subprocess
 * boot — against the checked-in fixture project (healthy case) and a
 * deliberately broken project (boot-failure case).
 */
class DoctorEndToEndTest extends TestCase
{
    private string $originalCwd;

    #[Before]
    public function rememberCwd(): void
    {
        $this->originalCwd = getcwd();
    }

    #[After]
    public function restoreCwd(): void
    {
        chdir($this->originalCwd);
    }

    public function test_the_known_good_fixture_project_is_reported_healthy(): void
    {
        chdir(__DIR__ . '/../Fixtures/sample-project');

        $command = new Doctor();

        ob_start();
        $exitCode = $command->handle([], []);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('PHP', $output);
        $this->assertStringContainsString('vendor/ installed', $output);
        $this->assertStringContainsString('Environment', $output);
        $this->assertStringContainsString('Boot — index.php booted cleanly', $output);
        $this->assertStringContainsString('Routes — 3 routes across 1 subdomain', $output);
        $this->assertStringContainsString('Container', $output);
        $this->assertStringContainsString('Project is healthy.', $output);
    }

    public function test_a_project_with_a_broken_boot_is_reported_unhealthy(): void
    {
        $cliVendorAutoload = realpath(__DIR__ . '/../../vendor/autoload.php');
        $this->assertNotFalse($cliVendorAutoload, 'expected the CLI\'s own vendor/autoload.php to exist for this fixture to borrow');

        $dir = sys_get_temp_dir() . '/arcos-doctor-broken-project-' . uniqid();
        mkdir($dir, 0755, true);

        file_put_contents($dir . '/composer.json', '{}');
        file_put_contents($dir . '/.env.example', "APP_ENV=\nAPP_KEY=\n");
        file_put_contents($dir . '/.env', "APP_ENV=local\nAPP_KEY=test\n");
        file_put_contents($dir . '/.gitignore', ".env\n");

        // setActiveSubdomain() on a subdomain that was never registered throws
        // synchronously during the include — a real misconfiguration a
        // developer could genuinely ship. (An undefined *middleware group*
        // referenced by registerSubdomain() would NOT be caught here: dumpRoutes()
        // silently skips an unresolved subdomain-level group rather than
        // throwing — see Router.php's "Group not defined yet — skip silently"
        // comment. Only a real dispatch()/buildChainAndExecute() call validates
        // that, and arcos-inspect.php deliberately never dispatches. Tracked as
        // a known gap in doctor's boot check, not exercised by this test.)
        file_put_contents($dir . '/index.php', <<<PHP
            <?php
            declare(strict_types=1);
            require_once '{$cliVendorAutoload}';
            use Arcos\Core\Routing\Router;
            use Arcos\Core\Http\Resolvers\PathUriResolver;
            \$router = new Router();
            \$router->registerSubdomain('api', new PathUriResolver());
            \$router->setActiveSubdomain('does-not-exist');
            PHP);

        chdir($dir);

        try {
            $command = new Doctor();

            ob_start();
            $exitCode = $command->handle([], []);
            $output = ob_get_clean();

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('has not been registered', $output);
            $this->assertStringContainsString('Project is unhealthy.', $output);
            // Boot failure short-circuits — routes/bindings are never reached.
            $this->assertStringNotContainsString('Routes —', $output);
            $this->assertStringNotContainsString('Container —', $output);
        } finally {
            chdir($this->originalCwd);
            foreach (glob($dir . '/*') ?: [] as $file) {
                unlink($file);
            }
            foreach (glob($dir . '/.*', GLOB_NOSORT) ?: [] as $file) {
                if (str_ends_with($file, '/.') || str_ends_with($file, '/..')) {
                    continue;
                }
                unlink($file);
            }
            rmdir($dir);
        }
    }
}
