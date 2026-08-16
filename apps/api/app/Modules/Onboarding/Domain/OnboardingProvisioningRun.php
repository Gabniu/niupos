<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class OnboardingProvisioningRun extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'initiated_by_user_id', 'idempotency_key',
        'command_fingerprint', 'status', 'dry_run', 'approval_required',
        'plan', 'correlation_id', 'approved_by_user_id', 'approved_at',
        'completed_at', 'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'approval_required' => 'boolean',
            'plan' => 'array',
            'approved_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
