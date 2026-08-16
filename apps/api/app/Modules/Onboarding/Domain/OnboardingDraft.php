<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class OnboardingDraft extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'channel_selection',
        'industry_profile',
        'answers',
        'current_step',
        'revision',
        'status',
        'last_idempotency_key',
        'tenant_id',
        'completed_at',
        'completion_idempotency_key',
        'company_id',
        'branch_id',
        'warehouse_id',
        'register_id',
        'location_completion_idempotency_key',
    ];

    protected function casts(): array
    {
        return ['answers' => 'array', 'revision' => 'integer', 'completed_at' => 'immutable_datetime'];
    }
}
