<?php

declare(strict_types=1);

namespace App\Modules\Sales\Routes;

use App\Modules\Sales\Application\Http\CashSaleCompletionController;
use App\Modules\Sales\Application\Http\FinalizeSaleController;
use App\Modules\Sales\Application\Http\SalesWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/sales')
    ->middleware(['api.session', 'tenant', 'permission:sales.checkout.create', 'throttle:sales-checkout'])
    ->group(function (): void {
        Route::get('price-books', [SalesWorkspaceController::class, 'priceBooks']);
        Route::post('quote', [SalesWorkspaceController::class, 'quote']);
        Route::post('finalize', FinalizeSaleController::class);
        Route::post('{sale}/cash-complete', CashSaleCompletionController::class)->whereUuid('sale');
    });
