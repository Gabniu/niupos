<?php
declare(strict_types=1);
namespace App\Modules\Identity\Application\Contracts;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;
interface TenantOwnerBootstrap { public function bootstrap(string $tenantId, User $owner, string $operatorReference = 'automated-test'): TenantMembership; }
