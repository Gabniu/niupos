<?php

declare(strict_types=1);

namespace App\Modules\Register\Domain;

enum DeviceStatus: string
{
    case PendingEnrollment = 'pending_enrollment';
    case Active = 'active';
    case Disabled = 'disabled';
}
