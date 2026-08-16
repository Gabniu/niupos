<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tenancy;

use App\Modules\Tenancy\Application\Contracts\TenantAwareJob;
use App\Modules\Tenancy\Application\Middleware\TenantJobMiddleware;
use App\Modules\Tenancy\Application\TenantCacheKey;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Application\TenantEventEnvelope;
use App\Modules\Tenancy\Application\TenantScope;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TenantBoundaryTest extends TestCase
{
    public const TENANT_ID = '01989f8e-7a42-7b41-8fc0-87e9b48e813e';

    #[Test]
    public function job_middleware_sets_and_clears_tenant_context(): void
    {
        $context = $this->app->make(TenantContext::class);
        $middleware = $this->app->make(TenantJobMiddleware::class);
        $job = new class implements TenantAwareJob
        {
            public function tenantId(): string
            {
                return TenantBoundaryTest::TENANT_ID;
            }
        };

        $seen = $middleware->handle($job, function () use ($context): string {
            return (string) $context->id();
        });

        self::assertSame(self::TENANT_ID, $seen);
        self::assertFalse($context->hasTenant());
    }

    #[Test]
    public function cache_keys_and_events_fail_without_context(): void
    {
        $this->expectException(LogicException::class);

        $this->app->make(TenantCacheKey::class)->for('catalogue:1');
    }

    #[Test]
    public function cache_keys_and_events_include_the_active_tenant(): void
    {
        $scope = $this->app->make(TenantScope::class);

        $result = $scope->run(
            \App\Modules\Tenancy\Domain\TenantId::fromString(self::TENANT_ID),
            function (): array {
                $key = $this->app->make(TenantCacheKey::class)->for('catalogue:1');
                $event = $this->app->make(TenantEventEnvelope::class)->wrap('catalogue.updated', ['id' => '1']);

                return [$key, $event];
            },
        );

        self::assertSame('tenant:'.self::TENANT_ID.':catalogue:1', $result[0]);
        self::assertSame(self::TENANT_ID, $result[1]['tenant_id']);
        self::assertSame('catalogue.updated', $result[1]['event_type']);
    }
}
