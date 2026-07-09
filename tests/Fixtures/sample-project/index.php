<?php

declare(strict_types=1);

// This fixture borrows the ARCOS-CLI package's own vendor/autoload.php (which
// has pylon/arcos via require-dev) rather than maintaining its own vendor/.
require_once __DIR__ . '/../../../vendor/autoload.php';

// App\ classes aren't covered by that autoloader's PSR-4 map (it only knows
// about Arcos\Cli\ and Arcos\), so they're required directly.
require_once __DIR__ . '/app/Controllers/ProductsController.php';
require_once __DIR__ . '/app/Middleware/LoggerMiddleware.php';
require_once __DIR__ . '/app/Middleware/VerbValidationMiddleware.php';
require_once __DIR__ . '/app/Middleware/RateLimitMiddleware.php';

use Arcos\Core\Container\Container;
use Arcos\Core\Http\Kernel;
use Arcos\Core\Http\Request;
use Arcos\Core\Http\Resolvers\PathUriResolver;
use Arcos\Core\Routing\Router;
use App\Controllers\ProductsController;
use App\Middleware\LoggerMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\VerbValidationMiddleware;

$env = parse_ini_file(__DIR__ . '/.env');

if ($env === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server configuration error.']);
    exit;
}

foreach ($env as $key => $value) {
    $_ENV[$key] = $value;
}

$container = new Container();
$container->singleton(ProductsController::class, fn($c) => new ProductsController());
$container->singleton(LoggerMiddleware::class, fn($c) => new LoggerMiddleware());
$container->bind(VerbValidationMiddleware::class, fn($c) => new VerbValidationMiddleware());
$container->bind(RateLimitMiddleware::class, fn($c) => new RateLimitMiddleware());

$router = new Router();

$router->registerSubdomain(
    subdomain:        'api',
    resolver:         new PathUriResolver(),
    middlewareGroups: ['api'],
);

$router->setActiveSubdomain('api');

(require __DIR__ . '/routes/api.php')($router);
(require __DIR__ . '/config/middleware.php')($router);

$router->boot(__DIR__);

// Inspection contract: when ARCOS_INSPECT is set, arcos-inspect.php reads
// $router from this script's global scope directly and never calls
// $kernel->handle() — so no real request is dispatched during inspection.
if (empty($_SERVER['ARCOS_INSPECT'])) {
    $kernel  = new Kernel($container, $router);
    $request = Request::fromGlobals($router->activeResolver());
    $kernel->handle($request);
}
