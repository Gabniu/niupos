<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class OnboardingSetupNotificationDelivery extends Model
{
    use HasUuids;

    /**
     * Delivery intents use the shorter persisted table name shared with the
     * dispatcher and migration. Eloquent's default would derive a different
     * table name from this aggregate's class name.
     */
    protected $table = 'onboarding_notification_deliveries';

    protected $fillable = [
        'tenant_id', 'notification_id', 'recipient_user_id', 'channel',
        'status', 'blocked_reason',
        'attempts', 'last_attempted_at', 'provider_message_id',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
            'last_attempted_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }
}
