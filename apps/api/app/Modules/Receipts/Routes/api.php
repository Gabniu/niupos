<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Routes;

use App\Modules\Receipts\Application\Http\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/receipts')->middleware(['api.session', 'tenant'])->group(function (): void {
    Route::get('{receipt}', [ReceiptController::class, 'show'])
        ->middleware(['permission:receipts.read', 'throttle:receipts-read']);
    Route::post('{receipt}/delivery-attempts', [ReceiptController::class, 'recordDelivery'])
        ->middleware(['permission:receipts.delivery.record', 'throttle:receipts-delivery']);
});
