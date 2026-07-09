<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Unit;

use Arcos\Cli\Application;
use Arcos\Cli\Tests\Concerns\UsesTempProject;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ApplicationTest extends TestCase
{
    use UsesTempProject;

    #[After]
    public function cleanup(): void
    {
        if (isset($this->tempProjectDir)) {
            $this->cleanupTempProjectDir();
        }
    }

    private function parseTokens(array $tokens): array
    {
        $app        = new Application(['arcos', 'help']);
        $reflection = new ReflectionClass($app);
        $method     = $reflection->getMethod('parseTokens');
        $method->setAccessible(true);

        return $method->invoke($app, $tokens);
    }

    public function test_unknown_command_returns_1_and_prints_an_error(): void
    {
        $app = new Application(['arcos', 'not-a-real-command']);

        $exitCode = -1;
        $output   = $this->captureOutput(function () use ($app, &$exitCode) {
            $exitCode = $app->run();
        });

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Unknown command: not-a-real-command', $output);
    }

    public function test_help_lists_known_commands_grouped_by_prefix(): void
    {
        $app = new Application(['arcos', 'help']);

        $output = $this->captureOutput(fn() => $app->run());

        $this->assertStringContainsString('make:controller', $output);
        $this->assertStringContainsString('inspect:routes', $output);
        $this->assertStringContainsString('dev:serve', $output);
        $this->assertStringContainsString('new', $output);
    }

    public function test_no_command_defaults_to_help(): void
    {
        $app = new Application(['arcos']);

        $exitCode = -1;
        $output   = $this->captureOutput(function () use ($app, &$exitCode) {
            $exitCode = $app->run();
        });

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('ARCOS CLI', $output);
    }

    public function test_parses_bare_flags_as_true(): void
    {
        [$args, $flags] = $this->parseTokens(['Products', '--register']);

        $this->assertSame(['Products'], $args);
        $this->assertSame(['register' => true], $flags);
    }

    public function test_parses_key_value_flags(): void
    {
        [$args, $flags] = $this->parseTokens(['--port=9000']);

        $this->assertSame([], $args);
        $this->assertSame(['port' => '9000'], $flags);
    }

    public function test_parses_mixed_args_and_flags_in_any_order(): void
    {
        [$args, $flags] = $this->parseTokens(['--register', 'Products', '--inject=Inventory,Payment']);

        $this->assertSame(['Products'], $args);
        $this->assertSame(['register' => true, 'inject' => 'Inventory,Payment'], $flags);
    }

    public function test_dispatches_positional_args_and_flags_to_the_real_command(): void
    {
        $this->createTempProjectDir();

        $app = new Application(['arcos', 'make:model', 'Product']);

        $exitCode = -1;
        $this->captureOutput(function () use ($app, &$exitCode) {
            $exitCode = $app->run();
        });

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->tempProjectDir . '/app/Models/Product.php');
    }
}
