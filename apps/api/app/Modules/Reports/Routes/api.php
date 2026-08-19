<?php

declare(strict_types=1);

namespace App\Modules\Reports\Routes;

use App\Modules\Reports\Application\Http\ReportsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/reports')->middleware(['api.session', 'tenant', 'permission:reports.read'])->group(function (): void {
    Route::get('summary', [ReportsController::class, 'summary']);
    Route::get('reconciliation', [ReportsController::class, 'reconciliation']);
    Route::get('payment-reconciliation', [ReportsController::class, 'paymentReconciliation']);
    Route::get('fiscal-submissions', [ReportsController::class, 'fiscalSubmissions']);
});
