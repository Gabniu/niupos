<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Modules\Tenancy\Application\Contracts\TenantAccessAuthorizer;
use App\Modules\Tenancy\Application\Middleware\ResolveTenantFromHeader;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

final class ResolveTenantFromHeaderTest extends TestCase
{
    #[Test]
    public function it_rejects_a_missing_tenant_header(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->app->make(ResolveTenantFromHeader::class)->handle(
            Request::create('/api/example'),
            fn (): Response => new Response,
        );
    }

    #[Test]
    public function it_establishes_and_then_clears_the_request_context(): void
    {
        $this->app->bind(TenantAccessAuthorizer::class, fn (): TenantAccessAuthorizer => new class implements TenantAccessAuthorizer
        {
            public function assertCanAccess(Request $request, string $tenantId): void {}
        });
        $context = $this->app->make(TenantContext::class);
        $request = Request::create('/api/example', server: [
            'HTTP_X_TENANT_ID' => '01989f8e-7a42-7b41-8fc0-87e9b48e813e',
        ]);

        $response = $this->app->make(ResolveTenantFromHeader::class)->handle(
            $request,
            function () use ($context): Response {
                return new Response((string) $context->id());
            },
        );

        self::assertSame('01989f8e-7a42-7b41-8fc0-87e9b48e813e', $response->getContent());
        self::assertFalse($context->hasTenant());
    }

    #[Test]
    public function it_denies_a_valid_tenant_until_iam_authorizes_membership(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $request = Request::create('/api/example', server: [
            'HTTP_X_TENANT_ID' => '01989f8e-7a42-7b41-8fc0-87e9b48e813e',
        ]);

        $this->app->make(ResolveTenantFromHeader::class)->handle(
            $request,
            fn (): Response => new Response,
        );
    }
}
