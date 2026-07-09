<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Concerns;

/**
 * Shared helper for tests that need a fake ARCOS project directory on disk —
 * every Command resolves paths through getcwd(), so tests chdir() into a
 * throwaway temp directory rather than mocking anything.
 */
trait UsesTempProject
{
    private string $originalCwd;
    private string $tempProjectDir;

    private function createTempProjectDir(bool $validProject = true): string
    {
        $this->originalCwd   = getcwd();
        $this->tempProjectDir = sys_get_temp_dir() . '/arcos-cli-test-' . uniqid();
        mkdir($this->tempProjectDir, 0755, true);
        // Resolve symlinks (e.g. macOS /tmp -> /private/tmp) so paths built from
        // this match what getcwd() reports after chdir() into the same directory.
        $this->tempProjectDir = realpath($this->tempProjectDir);

        if ($validProject) {
            file_put_contents($this->tempProjectDir . '/composer.json', '{}');
            file_put_contents(
                $this->tempProjectDir . '/index.php',
                "<?php\n\ndeclare(strict_types=1);\n\n// 3. Container — register all bindings\n\$container = new Container();\n",
            );
        }

        chdir($this->tempProjectDir);

        return $this->tempProjectDir;
    }

    private function cleanupTempProjectDir(): void
    {
        chdir($this->originalCwd);

        if (is_dir($this->tempProjectDir)) {
            $this->removeDirectory($this->tempProjectDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }

        foreach (glob($dir . '/.*', GLOB_NOSORT) ?: [] as $file) {
            if (str_ends_with($file, '/.') || str_ends_with($file, '/..')) {
                continue;
            }
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }

        rmdir($dir);
    }

    private function captureOutput(callable $fn): string
    {
        ob_start();
        $fn();

        return ob_get_clean();
    }
}
