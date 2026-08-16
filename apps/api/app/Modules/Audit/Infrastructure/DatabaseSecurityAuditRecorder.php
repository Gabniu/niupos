<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure;

use App\Modules\Audit\Application\Contracts\SecurityAuditRecorder;
use App\Modules\Audit\Application\SecurityAuditEvent;
use App\Modules\Audit\Domain\AuditEvent;
use App\Modules\Audit\Domain\TenantAuditEvent;
use Illuminate\Support\Facades\Date;

final class DatabaseSecurityAuditRecorder implements SecurityAuditRecorder
{
    public function record(SecurityAuditEvent $event): void
    {
        $model = $event->tenantId === null ? AuditEvent::class : TenantAuditEvent::class;

        $model::query()->create([
            'tenant_id' => $event->tenantId,
            'event_type' => $event->type,
            'actor_user_id' => $event->actorUserId,
            'metadata' => $event->metadata,
            'occurred_at' => Date::now(),
        ]);
    }
}
