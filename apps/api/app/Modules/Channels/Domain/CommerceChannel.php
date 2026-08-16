<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain;

enum CommerceChannel: string
{
    case Web = 'web';
    case Mobile = 'mobile';
}
