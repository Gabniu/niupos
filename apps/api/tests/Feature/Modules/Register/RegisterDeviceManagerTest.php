<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Register;

use App\Modules\Register\Application\Contracts\RegisterDeviceManager;
use App\Modules\Register\Domain\Register;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Branch;
use App\Modules\Tenancy\Domain\Company;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RegisterDeviceManagerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tenant_context_is_required(): void
    {
        $this->expectException(LogicException::class);
        $this->app->make(RegisterDeviceManager::class)->createRegister('branch', 'POS-1', 'Till 1');
    }

    #[Test]
    public function cross_tenant_branch_and_register_references_are_rejected(): void
    {
        [$tenantA, $branchA] = $this->tenantFixture('A');
        [$tenantB, $branchB] = $this->tenantFixture('B');

        try {
            $this->inTenant($tenantB, fn (RegisterDeviceManager $manager) => $manager->createRegister($branchA, 'LEAK', 'Leaked register'));
            self::fail('A cross-tenant branch reference was accepted.');
        } catch (DomainException) {
            self::assertTrue(true);
        }

        $registerA = $this->inTenant($tenantA, fn (RegisterDeviceManager $manager) => $manager->createRegister($branchA, 'POS-A', 'A register'));
        try {
            $this->inTenant($tenantB, fn (RegisterDeviceManager $manager) => $manager->issueDeviceEnrollment($registerA->getKey(), 'Leaked device', now()->addHour()));
            self::fail('A cross-tenant register reference was accepted.');
        } catch (DomainException) {
            self::assertTrue(true);
        }

        $this->expectException(QueryException::class);
        Register::query()->create([
            'tenant_id' => $tenantB,
            'branch_id' => $branchA,
            'code' => 'DB-LEAK',
            'name' => 'Invalid database register',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function enrollment_stores_only_a_digest_and_token_is_consumed_once(): void
    {
        [$tenantId, $branchId] = $this->tenantFixture('Enrollment');
        $register = $this->inTenant($tenantId, fn (RegisterDeviceManager $manager) => $manager->createRegister($branchId, 'POS-1', 'Front till'));
        $issued = $this->inTenant($tenantId, fn (RegisterDeviceManager $manager) => $manager->issueDeviceEnrollment($register->getKey(), 'Counter tablet', now()->addHour()));

        self::assertGreaterThanOrEqual(43, strlen($issued->token));
        $raw = $this->app['db']->table('devices')->where('id', $issued->device->getKey())->first();
        self::assertNotSame($issued->token, $raw->enrollment_token_digest);
        self::assertSame(hash('sha256', $issued->token), $raw->enrollment_token_digest);

        $enrolled = $this->inTenant($tenantId, fn (RegisterDeviceManager $manager) => $manager->consumeDeviceEnrollment($issued->token));
        self::assertSame('active', $enrolled->status);
        self::assertNull($enrolled->enrollment_token_digest);
        self::assertNotNull($enrolled->enrollment_consumed_at);

        $this->expectException(DomainException::class);
        $this->inTenant($tenantId, fn (RegisterDeviceManager $manager) => $manager->consumeDeviceEnrollment($issued->token));
    }

    #[Test]
    public function expired_token_is_rejected_without_enrolling_the_device(): void
    {
        Carbon::setTestNow('2026-08-08 10:00:00');
        [$tenantId, $branchId] = $this->tenantFixture('Expiry');
        $register = $this->inTenant($tenantId, fn (RegisterDeviceManager $manager) => $manager->createRegister($branchId, 'POS-1', 'Front till'));
        $issued = $this->inTenant($tenantId, fn (RegisterDeviceManager $manager) => $manager->issueDeviceEnrollment($register->getKey(), 'Tablet', now()->addMinute()));
        Carbon::setTestNow('2026-08-08 10:02:00');

        try {
            $this->inTenant($tenantId, fn (RegisterDeviceManager $manager) => $manager->consumeDeviceEnrollment($issued->token));
            self::fail('An expired enrollment token was accepted.');
        } catch (DomainException) {
            self::assertSame('pending_enrollment', $issued->device->refresh()->status);
            self::assertNull($issued->device->enrollment_consumed_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function active_device_resolution_is_tenant_scoped_and_public_identifier_is_immutable(): void
    {
        [$tenantA, $branchA] = $this->tenantFixture('Resolve A');
        [$tenantB] = $this->tenantFixture('Resolve B');
        $register = $this->inTenant($tenantA, fn (RegisterDeviceManager $manager) => $manager->createRegister($branchA, 'POS-A', 'Register A'));
        $issued = $this->inTenant($tenantA, fn (RegisterDeviceManager $manager) => $manager->issueDeviceEnrollment($register->getKey(), 'Device A', now()->addHour()));

        self::assertNull($this->inTenant($tenantA, fn (RegisterDeviceManager $manager) => $manager->resolveActiveDevice($issued->device->public_id)));
        $device = $this->inTenant($tenantA, fn (RegisterDeviceManager $manager) => $manager->consumeDeviceEnrollment($issued->token));
        self::assertSame($device->getKey(), $this->inTenant($tenantA, fn (RegisterDeviceManager $manager) => $manager->resolveActiveDevice($device->public_id))?->getKey());
        self::assertNull($this->inTenant($tenantB, fn (RegisterDeviceManager $manager) => $manager->resolveActiveDevice($device->public_id)));

        $this->expectException(LogicException::class);
        $device->public_id = '00000000-0000-0000-0000-000000000000';
        $device->save();
    }

    /** @return array{string, string} */
    private function tenantFixture(string $name): array
    {
        $tenant = Tenant::query()->create(['name' => "Tenant {$name}", 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $company = Company::query()->create(['tenant_id' => $tenant->getKey(), 'name' => "Company {$name}", 'status' => 'active']);
        $branch = Branch::query()->create([
            'tenant_id' => $tenant->getKey(), 'company_id' => $company->getKey(),
            'code' => "BR-{$name}", 'name' => "Branch {$name}", 'status' => 'active',
        ]);

        return [(string) $tenant->getKey(), (string) $branch->getKey()];
    }

    private function inTenant(string $tenantId, callable $callback): mixed
    {
        return $this->app->make(TenantScope::class)->run(
            TenantId::fromString($tenantId),
            fn () => $callback($this->app->make(RegisterDeviceManager::class)),
        );
    }
}
