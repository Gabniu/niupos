<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Domain\PermissionKey;
use Illuminate\Contracts\Auth\Authenticatable;

interface PermissionAuthorizer
{
    public function allows(Authenticatable $actor, PermissionKey $permission): bool;
}
