<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Unit;

use Arcos\Cli\Commands\Inspect\InspectContainer;
use Arcos\Cli\Tests\Concerns\UsesTempProject;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

class InspectContainerTest extends TestCase
{
    use UsesTempProject;

    #[After]
    public function cleanup(): void
    {
        if (isset($this->tempProjectDir)) {
            $this->cleanupTempProjectDir();
        }
    }

    private function runInspectContainer(string $indexContent): string
    {
        $dir = $this->createTempProjectDir();
        file_put_contents($dir . '/index.php', $indexContent);

        $command = new InspectContainer();

        return $this->captureOutput(fn() => $command->handle([], []));
    }

    public function test_reports_no_bindings_for_an_empty_index(): void
    {
        $output = $this->runInspectContainer("<?php\ndeclare(strict_types=1);\n");

        $this->assertStringContainsString('No bindings found', $output);
    }

    public function test_matches_singleton_and_bind_calls(): void
    {
        $output = $this->runInspectContainer(<<<'PHP'
            <?php
            declare(strict_types=1);
            use App\Services\InventoryService;
            use App\Middleware\VerbValidationMiddleware;
            $container->singleton(InventoryService::class, fn($c) => new InventoryService());
            $container->bind(VerbValidationMiddleware::class, fn($c) => new VerbValidationMiddleware());
            PHP);

        $this->assertStringContainsString('singleton', $output);
        $this->assertStringContainsString('App\Services\InventoryService', $output);
        $this->assertStringContainsString('bind', $output);
        $this->assertStringContainsString('App\Middleware\VerbValidationMiddleware', $output);
        $this->assertStringContainsString('2 bindings declared', $output);
    }

    public function test_resolves_short_class_name_via_use_statement(): void
    {
        $output = $this->runInspectContainer(<<<'PHP'
            <?php
            declare(strict_types=1);
            use App\Services\InventoryService;
            $container->singleton(InventoryService::class, fn($c) => new InventoryService());
            PHP);

        $this->assertStringContainsString('App\Services\InventoryService', $output);
    }

    public function test_passes_through_already_fully_qualified_class_names(): void
    {
        $output = $this->runInspectContainer(<<<'PHP'
            <?php
            declare(strict_types=1);
            $container->singleton(\App\Services\InventoryService::class, fn($c) => new \App\Services\InventoryService());
            PHP);

        $this->assertStringContainsString('App\Services\InventoryService', $output);
    }

    public function test_summarizes_multi_dependency_factories_with_ellipsis(): void
    {
        $output = $this->runInspectContainer(<<<'PHP'
            <?php
            declare(strict_types=1);
            use App\Controllers\ProductsController;
            use App\Services\InventoryService;
            $container->singleton(ProductsController::class, fn($c) => new ProductsController($c->make(InventoryService::class)));
            PHP);

        $this->assertStringContainsString('(...)', $output);
    }

    public function test_reports_1_binding_singular_noun(): void
    {
        $output = $this->runInspectContainer(<<<'PHP'
            <?php
            declare(strict_types=1);
            use App\Services\InventoryService;
            $container->singleton(InventoryService::class, fn($c) => new InventoryService());
            PHP);

        $this->assertStringContainsString('1 binding declared', $output);
    }
}
