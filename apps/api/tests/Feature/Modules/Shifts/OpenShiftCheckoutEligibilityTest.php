<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Shifts;

use App\Modules\Identity\Domain\User;
use App\Modules\Register\Domain\Register;
use App\Modules\Shifts\Application\Contracts\OpenShiftCheckoutEligibility;
use App\Modules\Shifts\Application\Data\EligibleOpenShift;
use App\Modules\Shifts\Application\DatabaseOpenShiftCheckoutEligibility;
use App\Modules\Shifts\Domain\Shift;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Branch;
use App\Modules\Tenancy\Domain\Company;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class OpenShiftCheckoutEligibilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function eligible_checkout_receives_only_stable_shift_context_inside_a_transaction(): void
    {
        [$tenant, $register, $user, $shift] = $this->fixture('Eligible');

        $result = $this->inTenant($tenant, fn (OpenShiftCheckoutEligibility $eligibility) => $eligibility
            ->withEligibleOpenShift($register, $user, function (EligibleOpenShift $eligible) use ($tenant, $register, $user, $shift): string {
                self::assertGreaterThan(0, DB::transactionLevel());
                self::assertSame($tenant, $eligible->tenantId);
                self::assertSame($shift, $eligible->shiftId);
                self::assertSame($register, $eligible->registerId);
                self::assertSame($user, $eligible->actorUserId);
                self::assertSame('KES', $eligible->currency);
                self::assertNotSame('', $eligible->openedAt->format(DATE_ATOM));

                return $eligible->shiftId;
            }));

        self::assertSame($shift, $result);
    }

    #[Test]
    public function missing_or_closed_shift_is_rejected_generically(): void
    {
        [$tenant, $register, $user, $shift] = $this->fixture('Unavailable');
        Shift::query()->whereKey($shift)->update(['status' => 'closed', 'closed_at' => now()]);

        $this->assertRejected($tenant, $register, $user);

        Shift::query()->whereKey($shift)->delete();
        $this->assertRejected($tenant, $register, $user);
    }

    #[Test]
    public function inactive_register_or_membership_is_rejected_generically(): void
    {
        [$tenant, $register, $user] = $this->fixture('Inactive');
        DB::table('registers')->where('tenant_id', $tenant)->where('id', $register)->update(['status' => 'inactive']);
        $this->assertRejected($tenant, $register, $user);

        DB::table('registers')->where('tenant_id', $tenant)->where('id', $register)->update(['status' => 'active']);
        DB::table('tenant_memberships')->where('tenant_id', $tenant)->where('user_id', $user)->update(['status' => 'inactive']);
        $this->assertRejected($tenant, $register, $user);
    }

    #[Test]
    public function tenant_register_and_actor_boundaries_do_not_leak_shift_existence(): void
    {
        [$tenantA, $registerA, $userA] = $this->fixture('Tenant A');
        [$tenantB, $registerB, $userB] = $this->fixture('Tenant B');

        $this->assertRejected($tenantA, $registerB, $userA);
        $this->assertRejected($tenantA, $registerA, $userB);
        $this->assertRejected($tenantB, $registerA, $userB);
    }

    #[Test]
    public function callback_owns_the_transaction_and_eligibility_query_has_locking_shape(): void
    {
        [$tenant, $register, $user, $shift] = $this->fixture('Lock');

        try {
            $this->inTenant($tenant, fn (OpenShiftCheckoutEligibility $eligibility) => $eligibility
                ->withEligibleOpenShift($register, $user, function () use ($shift): never {
                    Shift::query()->whereKey($shift)->update(['expected_cash_minor' => 99_999]);

                    throw new RuntimeException('Abort checkout.');
                }));
            self::fail('The checkout callback did not abort.');
        } catch (RuntimeException $exception) {
            self::assertSame('Abort checkout.', $exception->getMessage());
        }

        self::assertSame(1_000, Shift::query()->findOrFail($shift)->expected_cash_minor);

        $source = file_get_contents((new \ReflectionClass(DatabaseOpenShiftCheckoutEligibility::class))->getFileName());
        self::assertIsString($source);
        self::assertStringContainsString("->where('tenant_id', \$tenantId)", $source);
        self::assertStringContainsString("->where('register_id', \$registerId)", $source);
        self::assertStringContainsString("->where('status', 'open')", $source);
        self::assertStringContainsString('->lockForUpdate()', $source);
    }

    private function assertRejected(string $tenantId, string $registerId, string $actorUserId): void
    {
        try {
            $this->inTenant($tenantId, fn (OpenShiftCheckoutEligibility $eligibility) => $eligibility
                ->withEligibleOpenShift($registerId, $actorUserId, fn () => self::fail('Ineligible checkout callback ran.')));
            self::fail('Ineligible checkout was accepted.');
        } catch (DomainException $exception) {
            self::assertSame('Checkout requires an eligible open shift.', $exception->getMessage());
        }
    }

    /** @return array{string, string, string, string} */
    private function fixture(string $suffix): array
    {
        $tenant = Tenant::query()->create(['name' => "Tenant {$suffix}", 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $company = Company::query()->create(['tenant_id' => $tenant->getKey(), 'name' => "Company {$suffix}", 'status' => 'active']);
        $branch = Branch::query()->create(['tenant_id' => $tenant->getKey(), 'company_id' => $company->getKey(), 'code' => 'BR-'.md5($suffix), 'name' => "Branch {$suffix}", 'status' => 'active']);
        $register = Register::query()->create(['tenant_id' => $tenant->getKey(), 'branch_id' => $branch->getKey(), 'code' => 'POS-'.md5($suffix), 'name' => "Register {$suffix}", 'status' => 'active']);
        $user = User::factory()->create();
        DB::table('tenant_memberships')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $shift = Shift::query()->create([
            'tenant_id' => $tenant->getKey(), 'register_id' => $register->getKey(), 'opening_user_id' => $user->getKey(),
            'status' => 'open', 'currency' => 'KES', 'opening_float_minor' => 1_000,
            'expected_cash_minor' => 1_000, 'opened_at' => now(), 'idempotency_key' => 'open-'.md5($suffix),
        ]);

        return [(string) $tenant->getKey(), (string) $register->getKey(), (string) $user->getKey(), (string) $shift->getKey()];
    }

    private function inTenant(string $tenantId, callable $callback): mixed
    {
        return $this->app->make(TenantScope::class)->run(
            TenantId::fromString($tenantId),
            fn () => $callback($this->app->make(OpenShiftCheckoutEligibility::class)),
        );
    }
}
