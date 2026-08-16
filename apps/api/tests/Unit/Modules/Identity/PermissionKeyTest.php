<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity;

use App\Modules\Identity\Domain\PermissionKey;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PermissionKeyTest extends TestCase
{
    #[Test]
    public function it_accepts_lowercase_dotted_permission_keys(): void
    {
        self::assertSame('catalogue.products.read', (string) new PermissionKey('catalogue.products.read'));
    }

    #[Test]
    public function it_rejects_unscoped_or_malformed_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PermissionKey('Admin');
    }
}
