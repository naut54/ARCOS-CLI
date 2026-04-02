<?php

declare(strict_types=1);

namespace Arcos\Cli\Commands\Dev;

use Arcos\Cli\Command;
use Arcos\Cli\IO\Output;

class DevEnv extends Command
{
    public static function signature(): string
    {
        return 'dev:env';
    }

    public static function description(): string
    {
        return 'Validate .env against .env.example';
    }

    public function handle(array $args, array $flags): int
    {
        if (!$this->requireProjectContext()) {
            return 1;
        }

        $envPath        = $this->projectPath('.env');
        $envExamplePath = $this->projectPath('.env.example');
        $gitignorePath  = $this->projectPath('.gitignore');

        // Both files must exist
        if (!file_exists($envExamplePath)) {
            Output::error('.env.example not found. Create it to define the environment contract.');
            return 1;
        }

        if (!file_exists($envPath)) {
            Output::error('.env not found. Copy .env.example and fill in the values.');
            return 1;
        }

        $required = $this->parseEnvFile($envExamplePath);
        $actual   = $this->parseEnvFile($envPath);

        Output::header('Environment check');

        $errors = 0;

        foreach ($required as $key => $_) {
            if (!array_key_exists($key, $actual)) {
                Output::error(sprintf('  %-14s missing', $key));
                $errors++;
                continue;
            }

            if ($actual[$key] === '') {
                Output::error(sprintf('  %-14s empty', $key));
                $errors++;
                continue;
            }

            // Mask secrets — show only that the value is set
            $display = $this->isSensitive($key)
                ? '••••••••  (set, value hidden)'
                : $actual[$key];

            Output::success(sprintf('%-14s = %s', $key, $display));
        }

        // Check for extra keys in .env not present in .env.example
        $extras = array_diff_key($actual, $required);
        foreach (array_keys($extras) as $key) {
            Output::warn(sprintf('  %-14s present in .env but not declared in .env.example', $key));
        }

        // Check .gitignore
        Output::line();
        $this->checkGitignore($gitignorePath, $errors);

        Output::line();

        if ($errors === 0) {
            Output::success('All required variables are present.');
            return 0;
        }

        $noun = $errors === 1 ? 'variable' : 'variables';
        Output::error("{$errors} required {$noun} not set. The application will refuse to start.");
        return 1;
    }

    // Helpers

    /**
     * Parse an INI-style env file into a key => value map.
     * Returns empty string for keys declared without a value (KEY=).
     *
     * @return array<string, string>
     */
    private function parseEnvFile(string $path): array
    {
        $result = [];
        $lines  = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments
            if (str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $result[trim($key)] = trim($value);
        }

        return $result;
    }

    /**
     * Keys whose values should be masked in output.
     */
    private function isSensitive(string $key): bool
    {
        $sensitivePatterns = ['KEY', 'SECRET', 'PASSWORD', 'TOKEN', 'PASS'];

        foreach ($sensitivePatterns as $pattern) {
            if (str_contains(strtoupper($key), $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function checkGitignore(string $gitignorePath, int &$errors): void
    {
        if (!file_exists($gitignorePath)) {
            Output::warn('.gitignore not found. Make sure .env is not committed to version control.');
            return;
        }

        $content = file_get_contents($gitignorePath);
        $lines   = array_map('trim', explode("\n", $content));

        // Accept ".env" or "/.env" as valid gitignore entries
        $isIgnored = in_array('.env', $lines, strict: true)
            || in_array('/.env', $lines, strict: true);

        if ($isIgnored) {
            Output::success('.env is listed in .gitignore');
        } else {
            Output::warn('.env is NOT listed in .gitignore — it may be committed accidentally.');
        }
    }
}