<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Support;

use Arcos\Cli\Commands\NewProject;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real command end-to-end. The non-lite path shells out to
 * `composer install`, which needs Packagist access — that dependency on the
 * outside world is exactly why this is Support-tier, not Unit-tier.
 */
class NewProjectTest extends TestCase
{
    private string $originalCwd;
    private string $workDir;

    protected function setUp(): void
    {
        $this->originalCwd = getcwd();
        $this->workDir      = sys_get_temp_dir() . '/arcos-new-project-test-' . uniqid();
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

    public function test_lite_mode_scaffolds_a_full_structure_without_a_framework_dependency(): void
    {
        $command  = new NewProject();
        $exitCode = -1;

        ob_start();
        $exitCode = $command->handle(['my-lite-api'], ['lite' => true]);
        ob_end_clean();

        $this->assertSame(0, $exitCode);

        $project = $this->workDir . '/my-lite-api';
        $this->assertDirectoryExists($project . '/src/Core/Http');
        $this->assertDirectoryExists($project . '/app/Controllers');
        $this->assertDirectoryExists($project . '/tests/Unit');
        $this->assertFileExists($project . '/routes/api.php');
        $this->assertFileExists($project . '/config/middleware.php');
        $this->assertFileExists($project . '/app/Controllers/HealthController.php');
        $this->assertFileExists($project . '/.env.example');
        $this->assertFileExists($project . '/.env');
        $this->assertFileExists($project . '/.gitignore');

        $composerJson = json_decode(file_get_contents($project . '/composer.json'), associative: true);
        $this->assertArrayNotHasKey('pylon/arcos', $composerJson['require']);

        $index = file_get_contents($project . '/index.php');
        $this->assertStringContainsString('// $container = new Container();', $index);
    }

    public function test_refuses_to_scaffold_into_an_existing_directory(): void
    {
        mkdir($this->workDir . '/already-exists');

        $command = new NewProject();
        $exitCode = -1;
        $output = null;

        ob_start();
        $exitCode = $command->handle(['already-exists'], ['lite' => true]);
        $output = ob_get_clean();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Directory already exists', $output);
    }

    public function test_missing_project_name_returns_1(): void
    {
        $command = new NewProject();

        ob_start();
        $exitCode = $command->handle([], []);
        $output = ob_get_clean();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Missing argument', $output);
    }

    public function test_full_mode_requires_the_framework_and_runs_composer_install(): void
    {
        $command = new NewProject();

        ob_start();
        $exitCode = $command->handle(['my-full-api'], []);
        $output = ob_end_clean();

        $project = $this->workDir . '/my-full-api';
        $composerJson = json_decode(file_get_contents($project . '/composer.json'), associative: true);

        $this->assertArrayHasKey('pylon/arcos', $composerJson['require']);
        // vendor/ only appears if composer install actually reached Packagist and succeeded.
        $this->assertDirectoryExists($project . '/vendor', 'composer install should have populated vendor/ from the real pylon/arcos package on Packagist');
        $this->assertFileExists($project . '/vendor/autoload.php');
    }

    /**
     * Regression test: registerSubdomain(middlewareGroups: ['api']) in the
     * generated index.php requires config/middleware.php to actually define
     * that group. It used to be left commented out, so every fresh project
     * 500'd on its very first request — exactly the request the CLI's own
     * "Next steps" output tells the user to make.
     */
    public function test_a_freshly_scaffolded_project_actually_boots_and_serves_health(): void
    {
        $command = new NewProject();
        ob_start();
        $command->handle(['bootable-api'], []);
        ob_end_clean();

        $project = $this->workDir . '/bootable-api';
        file_put_contents($project . '/.env', "APP_ENV=local\nAPP_KEY=test-key\n");

        $port = 8300 + random_int(0, 400);
        $serverProcess = proc_open(
            sprintf('php -S localhost:%d -t %s %s/index.php', $port, escapeshellarg($project), escapeshellarg($project)),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        try {
            $deadline = microtime(true) + 3.0;
            $up = false;
            while (microtime(true) < $deadline) {
                $conn = @fsockopen('localhost', $port, timeout: 0.2);
                if ($conn !== false) {
                    fclose($conn);
                    $up = true;
                    break;
                }
                usleep(50_000);
            }
            $this->assertTrue($up, 'dev server did not start in time');

            $raw = file_get_contents("http://localhost:{$port}/health", context: stream_context_create([
                'http' => ['ignore_errors' => true],
            ]));
            $httpCode = 200;
            foreach ($http_response_header ?? [] as $header) {
                if (preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d+)/', $header, $m)) {
                    $httpCode = (int) $m[1];
                }
            }
        } finally {
            if (is_resource($serverProcess)) {
                proc_terminate($serverProcess);
                proc_close($serverProcess);
            }
        }

        $this->assertSame(200, $httpCode, "expected /health to return 200, got {$httpCode}. Body: {$raw}");
        $decoded = json_decode($raw, associative: true);
        $this->assertTrue($decoded['success']);
        $this->assertSame('ok', $decoded['data']['status']);
    }
}
