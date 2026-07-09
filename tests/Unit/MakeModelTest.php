<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Unit;

use Arcos\Cli\Commands\Make\MakeModel;
use Arcos\Cli\Tests\Concerns\UsesTempProject;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

class MakeModelTest extends TestCase
{
    use UsesTempProject;

    #[After]
    public function cleanup(): void
    {
        if (isset($this->tempProjectDir)) {
            $this->cleanupTempProjectDir();
        }
    }

    public function test_generates_a_plain_model_stub(): void
    {
        $dir = $this->createTempProjectDir();
        $command = new MakeModel();

        $exitCode = -1;
        $this->captureOutput(function () use ($command, &$exitCode) {
            $exitCode = $command->handle(['Product'], []);
        });

        $this->assertSame(0, $exitCode);
        $content = file_get_contents($dir . '/app/Models/Product.php');
        $this->assertStringContainsString('namespace App\Models;', $content);
        $this->assertStringContainsString('class Product', $content);
        $this->assertStringNotContainsString('extends', $content);
    }

    public function test_does_not_touch_index_php_and_prints_a_note_instead(): void
    {
        $dir = $this->createTempProjectDir();
        $originalIndex = file_get_contents($dir . '/index.php');
        $command = new MakeModel();

        $output = $this->captureOutput(fn() => $command->handle(['Product'], []));

        $this->assertSame($originalIndex, file_get_contents($dir . '/index.php'));
        $this->assertStringContainsString('No container registration required', $output);
    }

    public function test_refuses_to_overwrite_an_existing_file(): void
    {
        $dir = $this->createTempProjectDir();
        mkdir($dir . '/app/Models', 0755, true);
        file_put_contents($dir . '/app/Models/Product.php', 'original');

        $command = new MakeModel();
        $this->captureOutput(fn() => $command->handle(['Product'], []));

        $this->assertSame('original', file_get_contents($dir . '/app/Models/Product.php'));
    }
}
