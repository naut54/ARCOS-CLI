<?php

declare(strict_types=1);

namespace Arcos\Cli\Commands\Dev;

use Arcos\Cli\Command;
use Arcos\Cli\IO\Output;

class DevHealth extends Command
{
    private const int     DEFAULT_TIMEOUT = 5;
    private const string  DEFAULT_URL     = 'http://localhost:8000';
    private const string  HEALTH_PATH     = '/health';

    public static function signature(): string
    {
        return 'dev:health';
    }

    public static function description(): string
    {
        return 'Ping the /health endpoint and display service states';
    }

    public function handle(array $args, array $flags): int
    {
        $baseUrl     = isset($flags['url']) && is_string($flags['url'])
            ? rtrim($flags['url'], '/')
            : self::DEFAULT_URL;

        $healthUrl = $baseUrl . self::HEALTH_PATH;

        Output::header("Health check — {$healthUrl}");

        $response = $this->fetch($healthUrl);

        if ($response === null) {
            Output::error("Could not reach {$healthUrl}");
            Output::line();
            Output::comment('  Make sure the server is running: arcos dev:serve');
            return 1;
        }

        if ($response['http_code'] === 404) {
            Output::error('The /health endpoint does not exist in this project.');
            Output::line();
            Output::snippet(
                'Add a health route to routes/api.php:',
                "\$router->add('GET', '/health', HealthController::class, 'index');"
            );
            return 1;
        }

        $body = $response['body'];

        if (!isset($body['data'])) {
            Output::warn("Unexpected response shape from {$healthUrl}");
            Output::line();
            Output::comment(print_r($body, return: true));
            return 1;
        }

        $services = $body['data'];
        $overall  = $body['status'] ?? 'unknown';

        $this->renderTable($services);

        Output::line();
        $this->renderOverall($overall);

        return $overall === 'ok' ? 0 : 1;
    }

    // Rendering

    /**
     * @param array<int, array<string, mixed>> $services
     */
    private function renderTable(array $services): void
    {
        if (empty($services)) {
            Output::comment('  No services reported.');
            return;
        }

        // Column widths
        $serviceWidth = max(32, ...array_map(
            fn($s) => strlen($s['service'] ?? ''),
            $services
        ));

        $header = sprintf(
            "  %-{$serviceWidth}s  %-10s  %s",
            'SERVICE',
            'STATUS',
            'NOTE'
        );

        $divider = sprintf(
            "  %s  %s  %s",
            str_repeat('─', $serviceWidth),
            str_repeat('─', 10),
            str_repeat('─', 34)
        );

        Output::line($header);
        Output::line($divider);

        foreach ($services as $service) {
            $name   = $service['service'] ?? '—';
            $status = $service['status']  ?? 'unknown';
            $note   = $service['reason']  ?? '—';

            $statusColored = match ($status) {
                'ok'       => "\033[32m" . sprintf('%-10s', $status) . "\033[0m",
                'degraded' => "\033[33m" . sprintf('%-10s', $status) . "\033[0m",
                default    => "\033[31m" . sprintf('%-10s', $status) . "\033[0m",
            };

            printf(
                "  %-{$serviceWidth}s  %s  %s\n",
                $name,
                $statusColored,
                $note
            );
        }
    }

    private function renderOverall(string $status): void
    {
        $label = strtoupper($status);

        match ($status) {
            'ok'       => Output::success("Overall: {$label}"),
            'degraded' => Output::warn("Overall: {$label}"),
            default    => Output::error("Overall: {$label}"),
        };
    }

    // HTTP

    /**
     * Perform a GET request and return the decoded JSON body + HTTP code.
     * Returns null if the server is unreachable.
     *
     * @return array{http_code: int, body: array<mixed>}|null
     */
    private function fetch(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => self::DEFAULT_TIMEOUT,
                'ignore_errors'   => true, // Read body even on 4xx/5xx
                'follow_location' => true,
            ],
        ]);

        $raw = @file_get_contents($url, context: $context);

        if ($raw === false) {
            return null;
        }

        // Extract HTTP status code from response headers
        $httpCode = 200;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d+)/', $header, $matches)) {
                    $httpCode = (int) $matches[1];
                    break;
                }
            }
        }

        $decoded = json_decode($raw, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['http_code' => $httpCode, 'body' => []];
        }

        return ['http_code' => $httpCode, 'body' => $decoded];
    }
}