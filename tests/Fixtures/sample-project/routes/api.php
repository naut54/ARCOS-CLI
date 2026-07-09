<?php

declare(strict_types=1);

use Arcos\Core\Routing\Router;
use App\Controllers\ProductsController;

return function (Router $router): void {
    $router->subdomain('api', function (Router $router): void {
        $router->add('GET',  '/products',      ProductsController::class, 'index');
        $router->add('GET',  '/products/show', ProductsController::class, 'show');
        $router->add('POST', '/products',      ProductsController::class, 'store');
    });
};
