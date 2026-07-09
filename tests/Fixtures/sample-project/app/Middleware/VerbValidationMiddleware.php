<?php

declare(strict_types=1);

namespace App\Middleware;

use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;
use Arcos\Core\Middleware\MiddlewareInterface;
use Arcos\Core\Middleware\RouteAwareInterface;
use Arcos\Core\Helpers\ErrorHelper;

class VerbValidationMiddleware implements MiddlewareInterface, RouteAwareInterface
{
    private array $allowedMethods = [];

    public function withAllowedMethods(array $methods): static
    {
        $clone = clone $this;
        $clone->allowedMethods = $methods;

        return $clone;
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!in_array($request->method(), $this->allowedMethods, strict: true)) {
            return ErrorHelper::respond('RTE-002');
        }

        return $next($request);
    }
}
