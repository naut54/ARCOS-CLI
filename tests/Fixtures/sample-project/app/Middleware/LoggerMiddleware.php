<?php

declare(strict_types=1);

namespace App\Middleware;

use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;
use Arcos\Core\Middleware\MandatoryMiddlewareInterface;
use Arcos\Core\Middleware\MiddlewareInterface;

class LoggerMiddleware implements MiddlewareInterface, MandatoryMiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        return $next($request);
    }

    public function handleMandatory(Request $request, Response $response): Response
    {
        return $response;
    }
}
