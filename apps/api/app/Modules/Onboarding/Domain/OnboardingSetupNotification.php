<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class OnboardingSetupNotification extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'recipient_user_id', 'event_id', 'run_id', 'type',
        'title', 'message', 'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'immutable_datetime'];
    }
}
