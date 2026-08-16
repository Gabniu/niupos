<?php

declare(strict_types=1);

namespace App\Modules\Identity\Routes;

use App\Modules\Identity\Application\Http\AuthController;
use App\Modules\Identity\Application\Http\TenantIamAdministrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:iam-login');
    Route::get('federation/start', [AuthController::class, 'federationStart'])->middleware('throttle:iam-login');
    Route::get('federation/callback', [AuthController::class, 'federationCallback'])->middleware('throttle:iam-login');
    Route::middleware('api.session')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('tenants', [AuthController::class, 'tenants']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::post('mfa/totp/enrollment', [AuthController::class, 'beginTotpEnrollment']);
        Route::post('mfa/totp/confirmation', [AuthController::class, 'confirmTotpEnrollment'])
            ->middleware('throttle:iam-mfa');
        Route::post('mfa/elevation', [AuthController::class, 'elevateMfa'])
            ->middleware('throttle:iam-mfa');
    });
});

Route::prefix('api/v1/iam')->middleware(['api.session', 'tenant'])->group(function (): void {
    Route::post('roles', [TenantIamAdministrationController::class, 'createRole']);
    Route::put('roles/{roleId}/permissions', [TenantIamAdministrationController::class, 'replaceRolePermissions'])
        ->whereUuid('roleId');
    Route::put('memberships/{userId}', [TenantIamAdministrationController::class, 'assignMembership'])
        ->whereUuid('userId');
    Route::post('memberships/{userId}/ownership-transfer', [TenantIamAdministrationController::class, 'transferOwnership'])
        ->middleware('mfa.elevated')
        ->whereUuid('userId');
});
