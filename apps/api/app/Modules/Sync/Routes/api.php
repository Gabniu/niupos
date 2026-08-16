<?php

declare(strict_types=1);

namespace App\Modules\Sync\Routes;

use App\Modules\Sync\Application\Http\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/sync')->middleware(['api.session', 'tenant'])->group(function (): void {
    Route::get('bootstrap', [SyncController::class, 'bootstrap'])
        ->middleware(['permission:sync.use', 'throttle:sync-bootstrap']);
    Route::get('changes', [SyncController::class, 'changes'])
        ->middleware(['permission:sync.use', 'throttle:sync-pull']);
    Route::post('commands', [SyncController::class, 'commands'])
        ->middleware(['permission:sync.use', 'throttle:sync-commands']);
});
