<?php

namespace App\Http\Controllers;

use App\Support\MenuCatalog;
use Illuminate\Http\JsonResponse;

class MenuController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['items' => MenuCatalog::ITEMS]);
    }
}