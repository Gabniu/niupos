<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class OnboardingSetupEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'actor_user_id', 'run_id', 'type', 'status',
        'message', 'correlation_id', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
