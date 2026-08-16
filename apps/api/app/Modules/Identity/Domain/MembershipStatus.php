<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

enum MembershipStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
