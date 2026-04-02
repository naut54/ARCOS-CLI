<?php

declare(strict_types=1);

namespace Arcos\Cli\Commands\Inspect;

use Arcos\Cli\IO\Output;

/**
 * Calls the arcos-inspect.php subprocess to retrieve live router data.
 *
 * The subprocess boots index.php with ARCOS_INSPECT=1 set, which prevents
 * the kernel from dispatching a real request, then calls Router::dumpRoutes()
 * or Router::dumpGroups() and writes JSON to stdout.
 */
final class RuntimeBridge
{
    /**
     * Path to arcos-inspect.php, relative to the CLI package root.
     * Resolved from __DIR__ at call time so it works regardless of cwd.
     */
    private string $inspectScript;

    public function __construct()
    {
        // Resolve from src/Commands/Inspect/ up to the CLI package root, then into bin/
        // src/Commands/Inspect/ -3-> cli root, then /bin/arcos-inspect.php
        $this->inspectScript = dirname(__DIR__, 3) . '/bin/arcos-inspect.php';
    }

    /**
     * Run the subprocess and return decoded JSON data.
     *
     * @param  string $command     'routes' or 'groups'
     * @param  string $projectRoot Absolute path to the ARCOS project root
     * @return array{ok: true, data: array<mixed>}|array{ok: false, error: string}
     */
    public function run(string $command, string $projectRoot): array
    {
        if (!file_exists($this->inspectScript)) {
            return [
                'ok'    => false,
                'error' => "Inspection script not found at [{$this->inspectScript}].",
            ];
        }

        $cmd = sprintf(
            'php %s %s %s 2>&1',
            escapeshellarg($this->inspectScript),
            escapeshellarg($command),
            escapeshellarg($projectRoot)
        );

        // Separate stdout from stderr so we can tell success from failure
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $cmd = sprintf(
            'php %s %s %s',
            escapeshellarg($this->inspectScript),
            escapeshellarg($command),
            escapeshellarg($projectRoot)
        );

        $process = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            return ['ok' => false, 'error' => 'Failed to start inspection subprocess.'];
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $errorData = json_decode($stderr, associative: true);
            $message   = $errorData['message'] ?? $stderr;
            return ['ok' => false, 'error' => $message];
        }

        $data = json_decode($stdout, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['ok' => false, 'error' => 'Subprocess returned invalid JSON.'];
        }

        return ['ok' => true, 'data' => $data];
    }
}