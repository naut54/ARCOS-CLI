<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Unit;

use Arcos\Cli\Commands\Make\MakeController;
use Arcos\Cli\Tests\Concerns\UsesTempProject;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

class MakeControllerTest extends TestCase
{
    use UsesTempProject;

    #[After]
    public function cleanup(): void
    {
        if (isset($this->tempProjectDir)) {
            $this->cleanupTempProjectDir();
        }
    }

    public function test_generates_a_controller_stub_at_the_expected_path(): void
    {
        $dir = $this->createTempProjectDir();
        $command = new MakeController();

        $exitCode = -1;
        $this->captureOutput(function () use ($command, &$exitCode) {
            $exitCode = $command->handle(['Products'], []);
        });

        $this->assertSame(0, $exitCode);
        $path = $dir . '/app/Controllers/ProductsController.php';
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertStringContainsString('namespace App\Controllers;', $content);
        $this->assertStringContainsString('class ProductsController', $content);
        $this->assertStringContainsString('public function index(Request $request): Response', $content);
    }

    public function test_normalizes_a_name_that_already_includes_the_controller_suffix(): void
    {
        $dir = $this->createTempProjectDir();
        $command = new MakeController();

        $this->captureOutput(fn() => $command->handle(['ProductsController'], []));

        $this->assertFileExists($dir . '/app/Controllers/ProductsController.php');
    }

    public function test_refuses_to_overwrite_an_existing_file(): void
    {
        $dir = $this->createTempProjectDir();
        mkdir($dir . '/app/Controllers', 0755, true);
        file_put_contents($dir . '/app/Controllers/ProductsController.php', 'original content');

        $command = new MakeController();
        $exitCode = -1;
        $output = $this->captureOutput(function () use ($command, &$exitCode) {
            $exitCode = $command->handle(['Products'], []);
        });

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('already exists', $output);
        $this->assertSame('original content', file_get_contents($dir . '/app/Controllers/ProductsController.php'));
    }

    public function test_missing_name_argument_returns_1(): void
    {
        $this->createTempProjectDir();
        $command = new MakeController();

        $exitCode = -1;
        $output = $this->captureOutput(function () use ($command, &$exitCode) {
            $exitCode = $command->handle([], []);
        });

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Missing argument', $output);
    }

    public function test_inject_flag_adds_constructor_params_and_use_statements(): void
    {
        $dir = $this->createTempProjectDir();
        $command = new MakeController();

        $this->captureOutput(fn() => $command->handle(['Products'], ['inject' => 'InventoryService,PaymentService']));

        $content = file_get_contents($dir . '/app/Controllers/ProductsController.php');
        $this->assertStringContainsString('use App\Services\InventoryService;', $content);
        $this->assertStringContainsString('use App\Services\PaymentService;', $content);
        $this->assertStringContainsString('private readonly InventoryService $inventoryService,', $content);
        $this->assertStringContainsString('private readonly PaymentService $paymentService,', $content);
    }

    public function test_without_register_flag_prints_the_snippet_and_does_not_touch_index(): void
    {
        $dir = $this->createTempProjectDir();
        $originalIndex = file_get_contents($dir . '/index.php');
        $command = new MakeController();

        $output = $this->captureOutput(fn() => $command->handle(['Products'], []));

        $this->assertStringContainsString('To register this controller', $output);
        $this->assertStringContainsString('$container->singleton(ProductsController::class', $output);
        $this->assertSame($originalIndex, file_get_contents($dir . '/index.php'));
    }

    public function test_register_flag_inserts_the_binding_into_index_php(): void
    {
        $dir = $this->createTempProjectDir();
        $command = new MakeController();

        $this->captureOutput(fn() => $command->handle(['Products'], ['register' => true]));

        $index = file_get_contents($dir . '/index.php');
        $this->assertStringContainsString('use App\Controllers\ProductsController;', $index);
        $this->assertStringContainsString('$container->singleton(ProductsController::class, fn($c) => new ProductsController());', $index);
    }

    public function test_register_flag_includes_injected_services_via_container_make(): void
    {
        $dir = $this->createTempProjectDir();
        $command = new MakeController();

        $this->captureOutput(fn() => $command->handle(['Products'], ['register' => true, 'inject' => 'InventoryService']));

        $index = file_get_contents($dir . '/index.php');
        $this->assertStringContainsString('new ProductsController($c->make(InventoryService::class))', $index);
    }

    public function test_register_falls_back_to_snippet_when_container_anchor_is_missing(): void
    {
        $dir = $this->createTempProjectDir();
        file_put_contents($dir . '/index.php', "<?php\ndeclare(strict_types=1);\n// no container anchor here\n");

        $command = new MakeController();
        $output = $this->captureOutput(fn() => $command->handle(['Products'], ['register' => true]));

        $this->assertStringContainsString('Could not locate the container section', $output);
        $this->assertStringContainsString('To register this controller', $output);
    }
}
