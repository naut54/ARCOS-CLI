<?php

declare(strict_types=1);

use Arcos\Core\Middleware\MiddlewareLink;
use Arcos\Core\Routing\Router;
use App\Middleware\LoggerMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\VerbValidationMiddleware;

return function (Router $router): void {
    $router->always([
        new MiddlewareLink(LoggerMiddleware::class, isMandatory: true, canHaveGroup: false),
    ]);

    // Applied to every route in the 'api' subdomain via registerSubdomain()'s
    // middlewareGroups — not re-referenced per-route, which would apply it twice.
    $router->group('api', [
        new MiddlewareLink(VerbValidationMiddleware::class),
    ]);

    // Per-route only, to demonstrate the third chain layer distinctly.
    $router->middleware('GET', '/products/show', [
        new MiddlewareLink(RateLimitMiddleware::class),
    ]);
};
