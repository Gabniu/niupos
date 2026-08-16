<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Routes;

use App\Modules\Tenancy\Application\Http\TenantWorkspaceController;
use App\Modules\Tenancy\Application\Http\WorkspaceLocationsController;
use App\Modules\Tenancy\Application\Http\WorkspacePreferencesController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/dashboard')->middleware(['api.session', 'tenant'])->group(function (): void {
    Route::get('overview', [TenantWorkspaceController::class, 'overview']);
});

Route::prefix('api/v1/workspace')->middleware(['api.session', 'tenant'])->group(function (): void {
    Route::get('locations', [WorkspaceLocationsController::class, 'index']);
    Route::get('preferences', [WorkspacePreferencesController::class, 'show']);
});

Route::prefix('api/v1/workspace')->middleware(['api.session', 'tenant', 'permission:iam.roles.manage'])->group(function (): void {
    Route::put('preferences', [WorkspacePreferencesController::class, 'update']);
});
