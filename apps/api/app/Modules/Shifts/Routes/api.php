<?php

declare(strict_types=1);

namespace App\Modules\Shifts\Routes;

use App\Modules\Shifts\Application\Http\ShiftOperationsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/shifts')->middleware(['api.session', 'tenant'])->group(function (): void {
    Route::get('current', [ShiftOperationsController::class, 'current']);
    Route::post('open', [ShiftOperationsController::class, 'open']);
    Route::post('cash-movements', [ShiftOperationsController::class, 'movement']);
    Route::post('{shift}/close', [ShiftOperationsController::class, 'close'])->whereUuid('shift');
});
