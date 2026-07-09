<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Unit;

use Arcos\Cli\Commands\Doctor;
use Arcos\Cli\Tests\Concerns\UsesTempProject;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Exercises Doctor's individual check methods directly via reflection, with
 * controlled temp-project fixtures — everything here is fast and has no
 * subprocess/network dependency. checkBoot() (the one method that shells out
 * via RuntimeBridge) is deliberately not covered here; see
 * tests/Support/DoctorEndToEndTest.php for the real, full-boot path.
 */
class DoctorTest extends TestCase
{
    use UsesTempProject;

    #[After]
    public function cleanup(): void
    {
        if (isset($this->tempProjectDir)) {
            $this->cleanupTempProjectDir();
        }
    }

    private function invoke(string $method, ...$args): mixed
    {
        $command    = new Doctor();
        $reflection = new ReflectionMethod($command, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($command, ...$args);
    }

    // checkPhpVersion()

    public function test_php_version_check_passes_when_the_constraint_is_satisfied(): void
    {
        $dir = $this->createTempProjectDir(validProject: false);
        file_put_contents($dir . '/composer.json', json_encode(['require' => ['php' => '>=8.3']]));

        $result = $this->invoke('checkPhpVersion');

        $this->assertSame('ok', $result['status']);
        $this->assertStringContainsString('satisfies >=8.3', $result['message']);
    }

    public function test_php_version_check_fails_when_the_constraint_is_not_satisfied(): void
    {
        $dir = $this->createTempProjectDir(validProject: false);
        file_put_contents($dir . '/composer.json', json_encode(['require' => ['php' => '>=99.0']]));

        $result = $this->invoke('checkPhpVersion');

        $this->assertSame('fail', $result['status']);
        $this->assertStringContainsString('does not satisfy', $result['message']);
    }

    public function test_php_version_check_skips_an_unrecognized_constraint_form(): void
    {
        $dir = $this->createTempProjectDir(validProject: false);
        file_put_contents($dir . '/composer.json', json_encode(['require' => ['php' => '^8.3']]));

        $result = $this->invoke('checkPhpVersion');

        $this->assertSame('skip', $result['status']);
        $this->assertStringContainsString('Unrecognized php constraint', $result['message']);
    }

    public function test_php_version_check_skips_when_composer_json_is_missing(): void
    {
        $this->createTempProjectDir(validProject: false);

        $result = $this->invoke('checkPhpVersion');

        $this->assertSame('skip', $result['status']);
        $this->assertStringContainsString('composer.json not found', $result['message']);
    }

    // checkDependencies()

    public function test_dependencies_check_fails_without_vendor_autoload(): void
    {
        $this->createTempProjectDir();

        $result = $this->invoke('checkDependencies');

        $this->assertSame('fail', $result['status']);
        $this->assertStringContainsString('vendor/autoload.php not found', $result['message']);
    }

    public function test_dependencies_check_fails_without_pylon_arcos_installed(): void
    {
        $dir = $this->createTempProjectDir();
        mkdir($dir . '/vendor', 0755, true);
        touch($dir . '/vendor/autoload.php');

        $result = $this->invoke('checkDependencies');

        $this->assertSame('fail', $result['status']);
        $this->assertStringContainsString('pylon/arcos is not installed', $result['message']);
    }

    public function test_dependencies_check_warns_without_composer_lock(): void
    {
        $dir = $this->createTempProjectDir();
        mkdir($dir . '/vendor/pylon/arcos', 0755, true);
        touch($dir . '/vendor/autoload.php');

        $result = $this->invoke('checkDependencies');

        $this->assertSame('warn', $result['status']);
        $this->assertStringContainsString('composer.lock not found', $result['message']);
    }

    public function test_dependencies_check_passes_with_everything_present(): void
    {
        $dir = $this->createTempProjectDir();
        mkdir($dir . '/vendor/pylon/arcos', 0755, true);
        touch($dir . '/vendor/autoload.php');
        touch($dir . '/composer.lock');

        $result = $this->invoke('checkDependencies');

        $this->assertSame('ok', $result['status']);
    }

    // checkEnvironment()

    public function test_environment_check_fails_without_env_example(): void
    {
        $this->createTempProjectDir();

        $result = $this->invoke('checkEnvironment');

        $this->assertSame('fail', $result['status']);
        $this->assertStringContainsString('.env.example not found', $result['message']);
    }

    public function test_environment_check_fails_with_a_missing_required_value(): void
    {
        $dir = $this->createTempProjectDir();
        file_put_contents($dir . '/.env.example', "APP_ENV=\nAPP_KEY=\n");
        file_put_contents($dir . '/.env', "APP_ENV=local\n");

        $result = $this->invoke('checkEnvironment');

        $this->assertSame('fail', $result['status']);
        $this->assertStringContainsString('required', $result['message']);
    }

    public function test_environment_check_warns_when_gitignore_is_missing(): void
    {
        $dir = $this->createTempProjectDir();
        file_put_contents($dir . '/.env.example', "APP_ENV=\n");
        file_put_contents($dir . '/.env', "APP_ENV=local\n");

        $result = $this->invoke('checkEnvironment');

        $this->assertSame('warn', $result['status']);
        $this->assertStringContainsString('may not be gitignored', $result['message']);
    }

    public function test_environment_check_passes_when_everything_is_correct(): void
    {
        $dir = $this->createTempProjectDir();
        file_put_contents($dir . '/.env.example', "APP_ENV=\n");
        file_put_contents($dir . '/.env', "APP_ENV=local\n");
        file_put_contents($dir . '/.gitignore', ".env\n");

        $result = $this->invoke('checkEnvironment');

        $this->assertSame('ok', $result['status']);
    }

    // checkContainerBindings()

    public function test_container_bindings_check_warns_with_zero_bindings(): void
    {
        $dir = $this->createTempProjectDir();
        file_put_contents($dir . '/index.php', "<?php\ndeclare(strict_types=1);\n");

        $result = $this->invoke('checkContainerBindings');

        $this->assertSame('warn', $result['status']);
        $this->assertStringContainsString('no bindings declared', $result['message']);
    }

    public function test_container_bindings_check_passes_and_counts_bindings(): void
    {
        $dir = $this->createTempProjectDir();
        file_put_contents($dir . '/index.php', <<<'PHP'
            <?php
            declare(strict_types=1);
            use App\Services\InventoryService;
            $container->singleton(InventoryService::class, fn($c) => new InventoryService());
            PHP);

        $result = $this->invoke('checkContainerBindings');

        $this->assertSame('ok', $result['status']);
        $this->assertStringContainsString('1 binding declared', $result['message']);
    }

    // summarizeRoutes() — pure function, no filesystem/subprocess involved

    public function test_summarize_routes_warns_when_there_are_none(): void
    {
        $result = $this->invoke('summarizeRoutes', []);

        $this->assertSame('warn', $result['status']);
        $this->assertStringContainsString('no routes registered', $result['message']);
    }

    public function test_summarize_routes_counts_routes_and_subdomains(): void
    {
        $result = $this->invoke('summarizeRoutes', [
            ['subdomain' => 'api'],
            ['subdomain' => 'api'],
            ['subdomain' => 'admin'],
        ]);

        $this->assertSame('ok', $result['status']);
        $this->assertStringContainsString('3 routes across 2 subdomains', $result['message']);
    }

    // render()

    public function test_render_maps_each_status_to_the_right_output_style(): void
    {
        $cases = [
            'ok'   => "\033[32m",
            'warn' => "\033[33m",
            'fail' => "\033[31m",
        ];

        foreach ($cases as $status => $colorCode) {
            ob_start();
            $this->invoke('render', ['status' => $status, 'message' => "test-{$status}"]);
            $output = ob_get_clean();

            $this->assertStringContainsString("test-{$status}", $output);
            $this->assertStringContainsString($colorCode, $output);
        }
    }

    public function test_render_prefixes_the_message_with_a_label_when_given(): void
    {
        ob_start();
        $this->invoke('render', ['status' => 'ok', 'message' => 'all good'], 'Environment');
        $output = ob_get_clean();

        $this->assertStringContainsString('Environment — all good', $output);
    }
}
