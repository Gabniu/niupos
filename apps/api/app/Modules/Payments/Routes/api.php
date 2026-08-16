<?php

declare(strict_types=1);

namespace App\Modules\Payments\Routes;

use App\Modules\Payments\Application\Http\PaymentOperationsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/payments')->middleware(['api.session', 'tenant'])->group(function (): void {
    Route::post('attempts', [PaymentOperationsController::class, 'initiate'])
        ->middleware(['permission:payments.create', 'throttle:payments-operations']);
    Route::post('attempts/{attempt}/provider-result', [PaymentOperationsController::class, 'applyProviderResult'])
        ->middleware(['permission:payments.providerresults.manage', 'throttle:payments-provider-results'])
        ->whereUuid('attempt');
});
