<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Fixtures;

use Arcos\Cli\Commands\NewProject;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

/**
 * Exact-content snapshot of the generated HealthController.php — the one
 * hand-written file in arcos new's scaffold (everything else is boilerplate
 * config). Per docs/arcos-guidelines.md §10, a Fixtures-tier failure signals
 * wording drift worth a human look — it is advisory, not a build blocker.
 */
class NewProjectScaffoldSnapshotTest extends TestCase
{
    private string $originalCwd;
    private string $workDir;

    protected function setUp(): void
    {
        $this->originalCwd = getcwd();
        $this->workDir      = sys_get_temp_dir() . '/arcos-new-snapshot-test-' . uniqid();
        mkdir($this->workDir, 0755, true);
        chdir($this->workDir);
    }

    #[After]
    public function cleanup(): void
    {
        chdir($this->originalCwd);
        $this->removeDirectory($this->workDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }

        foreach (glob($dir . '/.*', GLOB_NOSORT) ?: [] as $file) {
            if (str_ends_with($file, '/.') || str_ends_with($file, '/..')) {
                continue;
            }
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }

        rmdir($dir);
    }

    public function test_health_controller_stub_matches_the_known_baseline(): void
    {
        ob_start();
        (new NewProject())->handle(['demo'], ['lite' => true]);
        ob_end_clean();

        $expected = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Controllers;

        use Arcos\Core\Helpers\ResponseHelper;
        use Arcos\Core\Http\Request;
        use Arcos\Core\Http\Response;

        class HealthController
        {
            public function __construct(
                // Inject services here to include their health() in the response
            ) {}

            public function index(Request $request): Response
            {
                $services = [
                    // $this->inventoryService->health(),
                    // $this->notificationService->health(),
                ];

                $overall = 'ok';

                foreach ($services as $service) {
                    if ($service['status'] === 'down') {
                        $overall = 'down';
                        break;
                    }

                    if ($service['status'] === 'degraded') {
                        $overall = 'degraded';
                    }
                }

                return ResponseHelper::ok([
                    'status'   => $overall,
                    'services' => $services,
                ]);
            }
        }
        PHP;

        $this->assertSame($expected, file_get_contents($this->workDir . '/demo/app/Controllers/HealthController.php'));
    }
}
