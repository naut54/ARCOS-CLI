<?php

declare(strict_types=1);

namespace App\Controllers;

use Arcos\Core\Helpers\ResponseHelper;
use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;

class ProductsController
{
    public function index(Request $request): Response
    {
        return ResponseHelper::ok([]);
    }

    public function show(Request $request): Response
    {
        return ResponseHelper::ok([['id' => $request->input('id')]]);
    }

    public function store(Request $request): Response
    {
        return ResponseHelper::created([]);
    }
}
