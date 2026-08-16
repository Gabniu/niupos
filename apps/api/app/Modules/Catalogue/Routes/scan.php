<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Routes;

use App\Modules\Catalogue\Application\Http\ScanController;
use App\Modules\Catalogue\Application\Http\ProductController;
use App\Modules\Catalogue\Application\Http\VariantController;
use Illuminate\Support\Facades\Route;

Route::post('api/v1/catalogue/scan', ScanController::class)
    ->middleware(['api.session', 'tenant', 'permission:catalogue.products.read', 'throttle:catalogue-scan']);

Route::get('api/v1/catalogue/variants/{variant}', [VariantController::class, 'show'])
    ->whereUuid('variant')
    ->middleware(['api.session', 'tenant', 'permission:catalogue.products.read']);

Route::get('api/v1/catalogue/products', [ProductController::class, 'index'])
    ->middleware(['api.session', 'tenant', 'permission:catalogue.products.read']);
