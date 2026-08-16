<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tenancy;

use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\TenantId;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TenantContextTest extends TestCase
{
    #[Test]
    public function it_requires_a_tenant_before_access(): void
    {
        $this->expectException(LogicException::class);

        (new TenantContext)->id();
    }

    #[Test]
    public function it_cannot_switch_tenants_inside_one_scope(): void
    {
        $context = new TenantContext;
        $context->set(TenantId::fromString('01989f8e-7a42-7b41-8fc0-87e9b48e813e'));

        $this->expectException(LogicException::class);

        $context->set(TenantId::fromString('01989f8e-901f-7d7d-89d1-aa1e42622d4b'));
    }
}
