<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Support;

use Arcos\Cli\Commands\Inspect\RuntimeBridge;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

class RuntimeBridgeTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../Fixtures/sample-project';

    private ?string $tempProjectDir = null;

    #[After]
    public function cleanup(): void
    {
        if ($this->tempProjectDir !== null && is_dir($this->tempProjectDir)) {
            foreach (glob($this->tempProjectDir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->tempProjectDir);
        }
    }

    public function test_routes_command_returns_real_json_from_the_fixture_project(): void
    {
        $bridge = new RuntimeBridge();
        $result = $bridge->run('routes', realpath(self::FIXTURE));

        $this->assertTrue($result['ok']);
        $this->assertIsArray($result['data']);
        $this->assertNotEmpty($result['data']);
        $this->assertSame('GET', $result['data'][0]['method']);
    }

    public function test_groups_command_returns_real_json_from_the_fixture_project(): void
    {
        $bridge = new RuntimeBridge();
        $result = $bridge->run('groups', realpath(self::FIXTURE));

        $this->assertTrue($result['ok']);
        $this->assertArrayHasKey('api', $result['data']);
    }

    public function test_missing_index_php_surfaces_a_clear_error(): void
    {
        $this->tempProjectDir = sys_get_temp_dir() . '/arcos-runtime-bridge-test-' . uniqid();
        mkdir($this->tempProjectDir, 0755, true);

        $bridge = new RuntimeBridge();
        $result = $bridge->run('routes', $this->tempProjectDir);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('index.php not found', $result['error']);
    }

    public function test_project_whose_index_never_sets_router_surfaces_a_clear_error(): void
    {
        $this->tempProjectDir = sys_get_temp_dir() . '/arcos-runtime-bridge-test-' . uniqid();
        mkdir($this->tempProjectDir, 0755, true);
        file_put_contents($this->tempProjectDir . '/index.php', "<?php\n// deliberately never sets \$router\n");

        $bridge = new RuntimeBridge();
        $result = $bridge->run('routes', $this->tempProjectDir);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('router is not available', $result['error']);
    }
}
