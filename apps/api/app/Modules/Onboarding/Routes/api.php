<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Routes;

use App\Modules\Onboarding\Application\Http\OnboardingDraftController;
use App\Modules\Onboarding\Application\Http\OnboardingProvisioningController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/onboarding')->middleware('api.session')->group(function (): void {
    Route::get('draft', [OnboardingDraftController::class, 'show']);
    Route::put('draft', [OnboardingDraftController::class, 'save'])->middleware('throttle:onboarding-draft');
    Route::post('complete', [OnboardingDraftController::class, 'complete'])->middleware('throttle:onboarding-draft');
    Route::post('pos-locations', [OnboardingDraftController::class, 'completeLocations'])->middleware('throttle:onboarding-draft');
    Route::middleware(['tenant', 'permission:onboarding.provision'])->group(function (): void {
        Route::post('provisioning-runs/preview', [OnboardingProvisioningController::class, 'preview'])->middleware('throttle:onboarding-draft');
        Route::get('setup-timeline', [OnboardingProvisioningController::class, 'timeline']);
        Route::get('provisioning-capabilities', [OnboardingProvisioningController::class, 'capabilities']);
        Route::get('setup-notifications', [OnboardingProvisioningController::class, 'notifications']);
        Route::post('setup-notifications/{notificationId}/read', [OnboardingProvisioningController::class, 'markNotificationRead'])->whereUuid('notificationId');
        Route::get('notification-preferences', [OnboardingProvisioningController::class, 'notificationPreferences']);
        Route::get('notification-deliveries', [OnboardingProvisioningController::class, 'notificationDeliveries']);
        Route::post('notification-deliveries/{deliveryId}/send', [OnboardingProvisioningController::class, 'sendNotificationDelivery'])->whereUuid('deliveryId')->middleware('throttle:onboarding-draft');
        Route::put('notification-preferences', [OnboardingProvisioningController::class, 'updateNotificationPreferences']);
        Route::get('provisioning-runs/{runId}', [OnboardingProvisioningController::class, 'show'])->whereUuid('runId');
        Route::post('provisioning-runs/{runId}/approve', [OnboardingProvisioningController::class, 'approve'])->whereUuid('runId')->middleware('throttle:onboarding-draft');
        Route::post('provisioning-runs/{runId}/process', [OnboardingProvisioningController::class, 'process'])->whereUuid('runId')->middleware('throttle:onboarding-draft');
    });
});
