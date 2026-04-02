<?php

declare(strict_types=1);

/**
 * ARCOS inspection subprocess entrypoint.
 *
 * Usage: php bin/arcos-inspect.php <command> <project-root>
 *
 * Commands:
 *   routes  — outputs JSON from Router::dumpRoutes()
 *   groups  — outputs JSON from Router::dumpGroups()
 *
 * Exit codes:
 *   0  success, JSON written to stdout
 *   1  failure, JSON error written to stderr
 */

// Arguments

if ($argc !== 3) {
    fwrite(STDERR, json_encode([
        'error'   => 'Invalid arguments',
        'message' => 'Usage: arcos-inspect.php <command> <project-root>',
    ]));
    exit(1);
}

$command     = $argv[1];
$projectRoot = rtrim($argv[2], '/');

if (!in_array($command, ['routes', 'groups'], strict: true)) {
    fwrite(STDERR, json_encode([
        'error'   => 'Unknown command',
        'message' => "Supported commands: routes, groups. Got: [{$command}]",
    ]));
    exit(1);
}

if (!is_dir($projectRoot)) {
    fwrite(STDERR, json_encode([
        'error'   => 'Invalid project root',
        'message' => "Directory does not exist: [{$projectRoot}]",
    ]));
    exit(1);
}

// Boot isolation

// Redirect output so that the boot sequence (index.php) cannot contaminate stdout.
// Any echo/header calls in user code will be swallowed here; only our json_encode
// reaches stdout.
ob_start();

// Suppress any errors written directly to stdout by the project's boot code.
// Errors that surface as exceptions are caught below.
set_error_handler(function (int $errno, string $errstr) use ($projectRoot): bool {
    fwrite(STDERR, json_encode([
        'error'   => 'PHP error during boot',
        'message' => "[{$errno}] {$errstr}",
    ]));
    // Returning true prevents the default PHP error handler from printing to stdout.
    return true;
});

// Project boot

// index.php must expose $router after its boot sequence completes.
// The inspection contract requires that index.php does NOT call $kernel->handle()
// when ARCOS_INSPECT is set — see "Contract with index.php" below.
$_SERVER['ARCOS_INSPECT'] = '1';

try {
    // The project root is passed explicitly; we do not rely on __DIR__ or getcwd().
    $indexPath = $projectRoot . '/index.php';

    if (!file_exists($indexPath)) {
        throw new RuntimeException("index.php not found at [{$indexPath}].");
    }

    // Execute index.php in the project root's scope.
    // $router must be available in the global scope after this include.
    (static function () use ($indexPath): void {
        include $indexPath;
    })();

    // index.php must have set $router in its own scope and made it accessible.
    // Because index.php runs as a plain include (not a function), $router is in
    // the global scope. We retrieve it explicitly.
    global $router;

    if (!isset($router) || !($router instanceof \Arcos\Core\Routing\Router)) {
        throw new RuntimeException(
            "\$router is not available after booting index.php. " .
            "Ensure index.php declares \$router before the kernel call."
        );
    }
} catch (\Throwable $e) {
    ob_end_clean();
    restore_error_handler();

    fwrite(STDERR, json_encode([
        'error'   => 'Boot failed',
        'message' => $e->getMessage(),
    ]));
    exit(1);
}

ob_end_clean();
restore_error_handler();

// Dump

try {
    $data = match ($command) {
        'routes' => $router->dumpRoutes(),
        'groups' => $router->dumpGroups(),
    };

    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, json_encode([
        'error'   => 'Dump failed',
        'message' => $e->getMessage(),
    ]));
    exit(1);
}