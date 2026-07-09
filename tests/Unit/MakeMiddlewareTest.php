<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Unit;

use Arcos\Cli\Commands\Make\MakeMiddleware;
use Arcos\Cli\Tests\Concerns\UsesTempProject;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

class MakeMiddlewareTest extends TestCase
{
    use UsesTempProject;

    #[After]
    public function cleanup(): void
    {
        if (isset($this->tempProjectDir)) {
            $this->cleanupTempProjectDir();
        }
    }

    public function test_default_stub_implements_only_middleware_interface(): void
    {
        $dir = $this->createTempProjectDir();
        $command = new MakeMiddleware();

        $this->captureOutput(fn() => $command->handle(['Auth'], []));

        $content = file_get_contents($dir . '/app/Middleware/AuthMiddleware.php');
        $this->assertStringContainsString('implements MiddlewareInterface', $content);
        $this->assertStringNotContainsString('MandatoryMiddlewareInterface', $content);
        $this->assertStringNotContainsString('RouteAwareInterface', $content);
        $this->assertStringContainsString('public function handle(Request $request, callable $next): Response', $content);
    }

    public function test_mandatory_flag_adds_the_interface_and_method(): void
    {
        $dir = $this->createTempProjectDir();
        $command = new MakeMiddleware();

        $this->captureOutput(fn() => $command->handle(['Audit'], ['mandatory' => true]));

        $content = file_get_contents($dir . '/app/Middleware/AuditMiddleware.php');
        $this->assertStringContainsString('implements MiddlewareInterface, MandatoryMiddlewareInterface', $content);
        $this->assertStringContainsString('public function handleMandatory(Request $request, Response $response): Response', $content);
    }

    public function test_route_aware_flag_adds_the_interface_and_method(): void
    {
        $dir = $this->createTempProjectDir();
        $command = new MakeMiddleware();

        $this->captureOutput(fn() => $command->handle(['Verb'], ['route-aware' => true]));

        $content = file_get_contents($dir . '/app/Middleware/VerbMiddleware.php');
        $this->assertStringContainsString('implements MiddlewareInterface, RouteAwareInterface', $content);
        $this->assertStringContainsString('public function withAllowedMethods(array $methods): static', $content);
    }

    public function test_both_flags_combined_add_both_interfaces_and_methods(): void
    {
        $dir = $this->createTempProjectDir();
        $command = new MakeMiddleware();

        $this->captureOutput(fn() => $command->handle(['Full'], ['mandatory' => true, 'route-aware' => true]));

        $content = file_get_contents($dir . '/app/Middleware/FullMiddleware.php');
        $this->assertStringContainsString('MiddlewareInterface, MandatoryMiddlewareInterface, RouteAwareInterface', $content);
        $this->assertStringContainsString('handleMandatory', $content);
        $this->assertStringContainsString('withAllowedMethods', $content);
    }

    public function test_register_flag_uses_bind_not_singleton(): void
    {
        $dir = $this->createTempProjectDir();
        $command = new MakeMiddleware();

        $this->captureOutput(fn() => $command->handle(['Auth'], ['register' => true]));

        $index = file_get_contents($dir . '/index.php');
        $this->assertStringContainsString('$container->bind(AuthMiddleware::class, fn($c) => new AuthMiddleware());', $index);
    }
}
