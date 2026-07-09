<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Unit;

use Arcos\Cli\Commands\Dev\DevEnv;
use Arcos\Cli\Tests\Concerns\UsesTempProject;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

class DevEnvTest extends TestCase
{
    use UsesTempProject;

    #[After]
    public function cleanup(): void
    {
        if (isset($this->tempProjectDir)) {
            $this->cleanupTempProjectDir();
        }
    }

    private function runDevEnv(): array
    {
        $command  = new DevEnv();
        $exitCode = -1;
        $output   = $this->captureOutput(function () use ($command, &$exitCode) {
            $exitCode = $command->handle([], []);
        });

        return [$exitCode, $output];
    }

    public function test_missing_env_example_returns_1(): void
    {
        $this->createTempProjectDir();

        [$exitCode, $output] = $this->runDevEnv();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('.env.example not found', $output);
    }

    public function test_missing_env_returns_1(): void
    {
        $dir = $this->createTempProjectDir();
        file_put_contents($dir . '/.env.example', "APP_ENV=\nAPP_KEY=\n");

        [$exitCode, $output] = $this->runDevEnv();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('.env not found', $output);
    }

    public function test_missing_required_key_is_reported_and_fails(): void
    {
        $dir = $this->createTempProjectDir();
        file_put_contents($dir . '/.env.example', "APP_ENV=\nAPP_KEY=\n");
        file_put_contents($dir . '/.env', "APP_ENV=local\n");

        [$exitCode, $output] = $this->runDevEnv();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('APP_KEY', $output);
        $this->assertStringContainsString('missing', $output);
    }

    public function test_empty_required_value_is_reported_and_fails(): void
    {
        $dir = $this->createTempProjectDir();
        file_put_contents($dir . '/.env.example', "APP_ENV=\nAPP_KEY=\n");
        file_put_contents($dir . '/.env', "APP_ENV=local\nAPP_KEY=\n");

        [$exitCode, $output] = $this->runDevEnv();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('empty', $output);
    }

    public function test_extra_key_in_env_is_a_warning_not_an_error(): void
    {
        $dir = $this->createTempProjectDir();
        file_put_contents($dir . '/.env.example', "APP_ENV=\nAPP_KEY=\n");
        file_put_contents($dir . '/.env', "APP_ENV=local\nAPP_KEY=secret\nEXTRA_KEY=surprise\n");
        file_put_contents($dir . '/.gitignore', ".env\n");

        [$exitCode, $output] = $this->runDevEnv();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('EXTRA_KEY', $output);
        $this->assertStringContainsString('present in .env but not declared', $output);
    }

    public function test_sensitive_keys_are_masked_in_output(): void
    {
        $dir = $this->createTempProjectDir();
        file_put_contents($dir . '/.env.example', "APP_ENV=\nAPP_KEY=\nAPI_SECRET=\nDB_PASSWORD=\nAUTH_TOKEN=\n");
        file_put_contents($dir . '/.env', "APP_ENV=local\nAPP_KEY=abc123\nAPI_SECRET=xyz\nDB_PASSWORD=pw\nAUTH_TOKEN=tok\n");
        file_put_contents($dir . '/.gitignore', ".env\n");

        [$exitCode, $output] = $this->runDevEnv();

        $this->assertSame(0, $exitCode);
        $this->assertStringNotContainsString('abc123', $output);
        $this->assertStringNotContainsString('xyz', $output);
        $this->assertStringNotContainsString('pw', $output);
        $this->assertStringNotContainsString('tok', $output);
        $this->assertStringContainsString('value hidden', $output);
        $this->assertStringContainsString('local', $output); // APP_ENV is not sensitive
    }

    public function test_gitignore_missing_env_entry_warns(): void
    {
        $dir = $this->createTempProjectDir();
        file_put_contents($dir . '/.env.example', "APP_ENV=\n");
        file_put_contents($dir . '/.env', "APP_ENV=local\n");
        file_put_contents($dir . '/.gitignore', "vendor/\n");

        [$exitCode, $output] = $this->runDevEnv();

        $this->assertStringContainsString('NOT listed in .gitignore', $output);
    }

    public function test_missing_gitignore_warns(): void
    {
        $dir = $this->createTempProjectDir();
        file_put_contents($dir . '/.env.example', "APP_ENV=\n");
        file_put_contents($dir . '/.env', "APP_ENV=local\n");

        [$exitCode, $output] = $this->runDevEnv();

        $this->assertStringContainsString('.gitignore not found', $output);
    }

    public function test_all_present_and_ignored_returns_0(): void
    {
        $dir = $this->createTempProjectDir();
        file_put_contents($dir . '/.env.example', "APP_ENV=\nAPP_KEY=\n");
        file_put_contents($dir . '/.env', "APP_ENV=local\nAPP_KEY=secret\n");
        file_put_contents($dir . '/.gitignore', ".env\n");

        [$exitCode, $output] = $this->runDevEnv();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('All required variables are present', $output);
    }
}
