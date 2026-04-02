<?php

declare(strict_types=1);

namespace Arcos\Cli\Commands\Dev;

use Arcos\Cli\Command;
use Arcos\Cli\IO\Output;

class DevServe extends Command
{
    public static function signature(): string
    {
        return 'dev:serve';
    }

    public static function description(): string
    {
        return 'Start the PHP built-in server';
    }

    public function handle(array $args, array $flags): int
    {
        if (!$this->requireProjectContext()) {
            return 1;
        }

        $port = isset($flags['port']) && is_string($flags['port'])
            ? (int) $flags['port']
            : 8000;

        if ($port < 1 || $port > 65535) {
            Output::error("Invalid port: {$port}. Must be between 1 and 65535.");
            return 1;
        }

        $address = "localhost:{$port}";

        Output::header('ARCOS dev server');
        Output::warn('Not intended for production use.');
        Output::line();
        Output::success("Listening on http://{$address}");
        Output::comment('  Press Ctrl+C to stop.');
        Output::line();

        // Hand off to PHP's built-in server — this call blocks until Ctrl+C
        passthru("php -S {$address} -t " . escapeshellarg(getcwd()), $exitCode);

        return $exitCode;
    }
}