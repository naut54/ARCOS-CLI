<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Unit;

use Arcos\Cli\Commands\Make\MakeService;
use Arcos\Cli\Tests\Concerns\UsesTempProject;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

class MakeServiceTest extends TestCase
{
    use UsesTempProject;

    #[After]
    public function cleanup(): void
    {
        if (isset($this->tempProjectDir)) {
            $this->cleanupTempProjectDir();
        }
    }

    public function test_generates_a_service_stub_extending_base_service(): void
    {
        $dir = $this->createTempProjectDir();
        $command = new MakeService();

        $exitCode = -1;
        $this->captureOutput(function () use ($command, &$exitCode) {
            $exitCode = $command->handle(['Inventory'], []);
        });

        $this->assertSame(0, $exitCode);
        $content = file_get_contents($dir . '/app/Services/InventoryService.php');
        $this->assertStringContainsString('class InventoryService extends BaseService', $content);
        $this->assertStringContainsString('public function health(): array', $content);
    }

    public function test_refuses_to_overwrite_an_existing_file(): void
    {
        $dir = $this->createTempProjectDir();
        mkdir($dir . '/app/Services', 0755, true);
        file_put_contents($dir . '/app/Services/InventoryService.php', 'original');

        $command = new MakeService();
        $exitCode = $this->handleAndCapture($command, ['Inventory'], []);

        $this->assertSame('original', file_get_contents($dir . '/app/Services/InventoryService.php'));
    }

    public function test_register_flag_inserts_singleton_binding(): void
    {
        $dir = $this->createTempProjectDir();
        $command = new MakeService();

        $this->captureOutput(fn() => $command->handle(['Inventory'], ['register' => true]));

        $index = file_get_contents($dir . '/index.php');
        $this->assertStringContainsString('use App\Services\InventoryService;', $index);
        $this->assertStringContainsString('$container->singleton(InventoryService::class, fn($c) => new InventoryService());', $index);
    }

    private function handleAndCapture(MakeService $command, array $args, array $flags): int
    {
        $exitCode = -1;
        $this->captureOutput(function () use ($command, $args, $flags, &$exitCode) {
            $exitCode = $command->handle($args, $flags);
        });

        return $exitCode;
    }
}
