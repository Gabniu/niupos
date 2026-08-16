<?php

declare(strict_types=1);

namespace App\Modules\Channels\Routes;

use App\Modules\Channels\Application\Http\ChannelRegistrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/channels')->middleware(['api.session', 'tenant', 'permission:channels.registrations.manage'])->group(function (): void {
    Route::get('registrations', [ChannelRegistrationController::class, 'index']);
    Route::post('registrations', [ChannelRegistrationController::class, 'create'])->middleware('throttle:channel-registration');
    Route::post('registrations/{registrationId}/request-approval', [ChannelRegistrationController::class, 'requestApproval'])->whereUuid('registrationId')->middleware('throttle:channel-registration');
});
