<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Unit;

use Arcos\Cli\Command;
use Arcos\Cli\Tests\Concerns\UsesTempProject;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

class CommandTest extends TestCase
{
    use UsesTempProject;

    #[After]
    public function cleanup(): void
    {
        if (isset($this->tempProjectDir)) {
            $this->cleanupTempProjectDir();
        }
    }

    private function concreteCommand(): Command
    {
        return new class extends Command {
            public static function signature(): string { return 'test:fixture'; }
            public static function description(): string { return 'fixture'; }
            public function handle(array $args, array $flags): int { return 0; }

            public function publicRequireProjectContext(): bool { return $this->requireProjectContext(); }
            public function publicProjectPath(string $relative): string { return $this->projectPath($relative); }
        };
    }

    public function test_require_project_context_is_true_for_a_valid_project(): void
    {
        $this->createTempProjectDir(validProject: true);
        $command = $this->concreteCommand();

        $result = null;
        $this->captureOutput(function () use ($command, &$result) {
            $result = $command->publicRequireProjectContext();
        });

        $this->assertTrue($result);
    }

    public function test_require_project_context_is_false_and_prints_an_error_otherwise(): void
    {
        $this->createTempProjectDir(validProject: false);
        $command = $this->concreteCommand();

        $result = null;
        $output  = $this->captureOutput(function () use ($command, &$result) {
            $result = $command->publicRequireProjectContext();
        });

        $this->assertFalse($result);
        $this->assertStringContainsString('Not an ARCOS project', $output);
    }

    public function test_project_path_resolves_relative_to_cwd(): void
    {
        $dir = $this->createTempProjectDir();
        $command = $this->concreteCommand();

        $this->assertSame($dir . '/app/Controllers/FooController.php', $command->publicProjectPath('app/Controllers/FooController.php'));
        $this->assertSame($dir . '/app/Controllers/FooController.php', $command->publicProjectPath('/app/Controllers/FooController.php'));
    }
}
