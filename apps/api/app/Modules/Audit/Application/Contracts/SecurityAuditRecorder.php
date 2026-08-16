<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Contracts;

use App\Modules\Audit\Application\SecurityAuditEvent;

interface SecurityAuditRecorder
{
    public function record(SecurityAuditEvent $event): void;
}
