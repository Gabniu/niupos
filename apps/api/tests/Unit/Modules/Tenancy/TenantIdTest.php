<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tenancy;

use App\Modules\Tenancy\Domain\TenantId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TenantIdTest extends TestCase
{
    #[Test]
    public function it_normalizes_and_compares_uuid_values(): void
    {
        $id = TenantId::fromString('01989F8E-7A42-7B41-8FC0-87E9B48E813E');

        self::assertSame('01989f8e-7a42-7b41-8fc0-87e9b48e813e', (string) $id);
        self::assertTrue($id->equals(TenantId::fromString((string) $id)));
    }

    #[Test]
    public function it_rejects_non_uuid_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TenantId::fromString('tenant-one');
    }
}
