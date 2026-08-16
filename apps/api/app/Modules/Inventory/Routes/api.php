<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Routes;

use App\Modules\Inventory\Application\Http\InventoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/inventory')->middleware(['api.session', 'tenant', 'permission:inventory.stock.read'])->group(function (): void {
    Route::get('balances', [InventoryController::class, 'index']);
});
