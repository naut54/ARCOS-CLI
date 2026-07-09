<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Unit;

use Arcos\Cli\Commands\Dev\DevServe;
use Arcos\Cli\Tests\Concerns\UsesTempProject;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

/**
 * Only port validation is covered here — DevServe::handle() hands off to a
 * blocking passthru('php -S ...') call once validation passes, which is not
 * something a Unit test should ever reach.
 */
class DevServeTest extends TestCase
{
    use UsesTempProject;

    #[After]
    public function cleanup(): void
    {
        if (isset($this->tempProjectDir)) {
            $this->cleanupTempProjectDir();
        }
    }

    public function test_rejects_a_port_of_zero_before_starting_the_server(): void
    {
        $this->createTempProjectDir();
        $command = new DevServe();

        $exitCode = -1;
        $output   = $this->captureOutput(function () use ($command, &$exitCode) {
            $exitCode = $command->handle([], ['port' => '0']);
        });

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Invalid port', $output);
    }

    public function test_rejects_a_port_above_65535_before_starting_the_server(): void
    {
        $this->createTempProjectDir();
        $command = new DevServe();

        $exitCode = -1;
        $output   = $this->captureOutput(function () use ($command, &$exitCode) {
            $exitCode = $command->handle([], ['port' => '99999']);
        });

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Invalid port', $output);
    }

    public function test_requires_project_context_before_validating_port(): void
    {
        $this->createTempProjectDir(validProject: false);
        $command = new DevServe();

        $exitCode = -1;
        $output   = $this->captureOutput(function () use ($command, &$exitCode) {
            $exitCode = $command->handle([], ['port' => '0']);
        });

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Not an ARCOS project', $output);
    }
}
