<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Shifts;

use App\Modules\Identity\Domain\User;
use App\Modules\Register\Domain\Register;
use App\Modules\Shifts\Application\Contracts\ShiftCashManager;
use App\Modules\Shifts\Domain\CashMovement;
use App\Modules\Shifts\Domain\Shift;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Branch;
use App\Modules\Tenancy\Domain\Company;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ShiftCashManagerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function shift_lifecycle_cash_arithmetic_and_variance_are_atomic(): void
    {
        [$tenant, $register, $user] = $this->fixture('Lifecycle');
        $shift = $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->openShift($register, $user, 10_000, 'kes', 'open-1'));

        self::assertSame('open', $shift->status);
        self::assertSame('KES', $shift->currency);
        self::assertSame(10_000, $shift->expected_cash_minor);

        $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->recordCashMovement($shift->getKey(), 'pay_in', 2_500, 'Change top-up', $user, 'move-1'));
        $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->recordCashMovement($shift->getKey(), 'pay_out', 1_000, 'Petty cash', $user, 'move-2'));
        $closed = $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->closeShift($shift->getKey(), $user, 11_400));

        self::assertSame('closed', $closed->status);
        self::assertSame(11_500, $closed->expected_cash_minor);
        self::assertSame(11_400, $closed->counted_cash_minor);
        self::assertSame(-100, $closed->variance_minor);
        self::assertNotNull($closed->closed_at);

        $this->expectException(DomainException::class);
        $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->recordCashMovement($shift->getKey(), 'pay_in', 1, 'Too late', $user, 'move-3'));
    }

    #[Test]
    public function register_can_have_only_one_open_shift_at_application_and_database_boundaries(): void
    {
        [$tenant, $register, $user] = $this->fixture('Unique');
        $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->openShift($register, $user, 0, 'KES', 'open-a'));

        try {
            $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->openShift($register, $user, 0, 'KES', 'open-b'));
            self::fail('A second open shift was accepted.');
        } catch (DomainException) {
            self::assertTrue(true);
        }

        $this->expectException(QueryException::class);
        Shift::query()->create([
            'tenant_id' => $tenant, 'register_id' => $register, 'opening_user_id' => $user,
            'status' => 'open', 'currency' => 'KES', 'opening_float_minor' => 0,
            'expected_cash_minor' => 0, 'opened_at' => now(), 'idempotency_key' => 'raw-second',
        ]);
    }

    #[Test]
    public function repeated_idempotency_keys_return_the_original_result_and_conflicts_are_rejected(): void
    {
        [$tenant, $register, $user] = $this->fixture('Idempotency');
        $first = $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->openShift($register, $user, 500, 'KES', 'open-key'));
        $again = $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->openShift($register, $user, 500, 'KES', 'open-key'));
        self::assertSame($first->getKey(), $again->getKey());

        $movement = $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->recordCashMovement($first->getKey(), 'pay_in', 100, 'Float', $user, 'movement-key'));
        $repeated = $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->recordCashMovement($first->getKey(), 'pay_in', 100, 'Float', $user, 'movement-key'));
        self::assertSame($movement->getKey(), $repeated->getKey());
        self::assertSame(600, $first->refresh()->expected_cash_minor);
        self::assertSame(1, CashMovement::query()->where('tenant_id', $tenant)->count());

        $this->expectException(DomainException::class);
        $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->recordCashMovement($first->getKey(), 'pay_in', 101, 'Different', $user, 'movement-key'));
    }

    #[Test]
    public function tenant_boundaries_and_active_memberships_are_enforced(): void
    {
        [$tenantA, $registerA, $userA] = $this->fixture('Tenant A');
        [$tenantB, , $userB] = $this->fixture('Tenant B');

        foreach ([
            fn () => $this->inTenant($tenantB, fn (ShiftCashManager $manager) => $manager->openShift($registerA, $userB, 0, 'KES', 'cross-register')),
            fn () => $this->inTenant($tenantA, fn (ShiftCashManager $manager) => $manager->openShift($registerA, $userB, 0, 'KES', 'cross-user')),
        ] as $operation) {
            try {
                $operation();
                self::fail('A cross-tenant reference was accepted.');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }

        $shift = $this->inTenant($tenantA, fn (ShiftCashManager $manager) => $manager->openShift($registerA, $userA, 0, 'KES', 'valid'));
        $this->expectException(DomainException::class);
        $this->inTenant($tenantB, fn (ShiftCashManager $manager) => $manager->recordCashMovement($shift->getKey(), 'pay_in', 1, 'Cross-tenant', $userB, 'cross-shift'));
    }

    #[Test]
    public function cash_movements_are_append_only_and_invalid_money_inputs_are_rejected(): void
    {
        [$tenant, $register, $user] = $this->fixture('Append');
        $shift = $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->openShift($register, $user, 100, 'KES', 'open'));
        $movement = $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->recordCashMovement($shift->getKey(), 'pay_out', 25, 'Expense', $user, 'move'));

        try {
            $movement->reason = 'Rewritten';
            $movement->save();
            self::fail('A cash movement was updated.');
        } catch (LogicException) {
            self::assertTrue(true);
        }

        try {
            $this->app['db']->table('cash_movements')->where('id', $movement->getKey())->delete();
            self::fail('A cash movement was deleted.');
        } catch (QueryException) {
            self::assertTrue(true);
        }

        foreach ([
            fn () => $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->openShift($register, $user, -1, 'KES', 'negative-open')),
            fn () => $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->recordCashMovement($shift->getKey(), 'pay_in', 0, 'Zero', $user, 'zero')),
            fn () => $this->inTenant($tenant, fn (ShiftCashManager $manager) => $manager->closeShift($shift->getKey(), $user, -1)),
        ] as $operation) {
            try {
                $operation();
                self::fail('An invalid monetary input was accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    /** @return array{string, string, string} */
    private function fixture(string $suffix): array
    {
        $tenant = Tenant::query()->create(['name' => "Tenant {$suffix}", 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $company = Company::query()->create(['tenant_id' => $tenant->getKey(), 'name' => "Company {$suffix}", 'status' => 'active']);
        $branch = Branch::query()->create(['tenant_id' => $tenant->getKey(), 'company_id' => $company->getKey(), 'code' => 'BR-'.md5($suffix), 'name' => "Branch {$suffix}", 'status' => 'active']);
        $register = Register::query()->create(['tenant_id' => $tenant->getKey(), 'branch_id' => $branch->getKey(), 'code' => 'POS-'.md5($suffix), 'name' => "Register {$suffix}", 'status' => 'active']);
        $user = User::factory()->create();
        $this->app['db']->table('tenant_memberships')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [(string) $tenant->getKey(), (string) $register->getKey(), (string) $user->getKey()];
    }

    private function inTenant(string $tenantId, callable $callback): mixed
    {
        return $this->app->make(TenantScope::class)->run(
            TenantId::fromString($tenantId),
            fn () => $callback($this->app->make(ShiftCashManager::class)),
        );
    }
}
