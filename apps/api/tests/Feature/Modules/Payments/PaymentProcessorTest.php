<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Payments;

use App\Modules\Identity\Domain\User;
use App\Modules\Payments\Application\Contracts\PaymentProcessor;
use App\Modules\Payments\Application\Contracts\PaymentSettlementReader;
use App\Modules\Payments\Application\Contracts\SalePaymentLookup;
use App\Modules\Payments\Application\Data\PayableSale;
use App\Modules\Payments\Domain\PaymentAllocation;
use App\Modules\Payments\Domain\PaymentAttempt;
use App\Modules\Payments\PaymentsServiceProvider;
use App\Modules\Register\Domain\Register;
use App\Modules\Sales\Domain\Sale;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Branch;
use App\Modules\Tenancy\Domain\Company;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use App\Modules\Tenancy\Domain\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

final class PaymentProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [PaymentsServiceProvider::class];
    }

    public function test_cash_succeeds_allocates_once_and_replays_safely(): void
    {
        $fixture = $this->fixture('cash', 11600, 'KES');
        $this->bindSale($fixture);

        $this->inTenant($fixture['tenant'], function (PaymentProcessor $payments) use ($fixture): void {
            $first = $payments->initiate($fixture['sale'], 'cash', 11600, 'kes', $fixture['user'], 'pay-cash-1');
            $replay = $payments->initiate($fixture['sale'], 'cash', 11600, 'kes', $fixture['user'], 'pay-cash-1');
            self::assertSame($first->attemptId, $replay->attemptId);
            self::assertSame('succeeded', $first->status);
            self::assertSame(1, PaymentAttempt::query()->count());
            self::assertSame(11600, PaymentAllocation::query()->sum('amount_minor'));
            self::assertTrue($this->app->make(PaymentSettlementReader::class)->isFullyPaid($fixture['sale'], 'KES', 11600));
        });
    }

    public function test_mpesa_moves_from_pending_to_success_once(): void
    {
        $fixture = $this->fixture('mpesa-success', 5000, 'KES');
        $this->bindSale($fixture);
        $fingerprint = hash('sha256', 'normalized-provider-result');

        $this->inTenant($fixture['tenant'], function (PaymentProcessor $payments) use ($fixture, $fingerprint): void {
            $pending = $payments->initiate($fixture['sale'], 'mpesa', 5000, 'KES', $fixture['user'], 'pay-mpesa-1', ['customer_reference' => '254700000001']);
            self::assertSame('pending', $pending->status);
            self::assertSame('succeeded', $payments->applyProviderResult($pending->attemptId, 'succeeded', 'MPESA-ABC-001', $fingerprint)->status);
            self::assertSame('succeeded', $payments->applyProviderResult($pending->attemptId, 'succeeded', 'MPESA-ABC-001', $fingerprint)->status);
            self::assertSame(1, PaymentAllocation::query()->count());
        });
    }

    public function test_mpesa_failure_is_terminal_and_does_not_allocate(): void
    {
        $fixture = $this->fixture('mpesa-failed', 750, 'KES');
        $this->bindSale($fixture);

        $this->inTenant($fixture['tenant'], function (PaymentProcessor $payments) use ($fixture): void {
            $pending = $payments->initiate($fixture['sale'], 'mpesa', 750, 'KES', $fixture['user'], 'pay-mpesa-failed');
            $failed = $payments->applyProviderResult($pending->attemptId, 'failed', 'MPESA-FAILED-1', hash('sha256', 'failed'));
            self::assertSame('failed', $failed->status);
            self::assertSame(0, PaymentAllocation::query()->count());

            $this->expectException(RuntimeException::class);
            $payments->applyProviderResult($pending->attemptId, 'succeeded', 'MPESA-LATE-1', hash('sha256', 'late'));
        });
    }

    public function test_conflicts_amount_currency_overpayment_and_cross_tenant_are_rejected(): void
    {
        $fixture = $this->fixture('guards', 1000, 'KES');
        $this->bindSale($fixture);

        $this->inTenant($fixture['tenant'], function (PaymentProcessor $payments) use ($fixture): void {
            foreach ([[999, 'KES'], [1001, 'KES'], [1000, 'USD']] as [$amount, $currency]) {
                try {
                    $payments->initiate($fixture['sale'], 'cash', $amount, $currency, $fixture['user'], "invalid-{$amount}-{$currency}");
                    self::fail('An inexact payment should fail.');
                } catch (RuntimeException) {
                    self::assertSame(0, PaymentAttempt::query()->count());
                }
            }
            $payments->initiate($fixture['sale'], 'cash', 1000, 'KES', $fixture['user'], 'paid-once');
            try {
                $payments->initiate($fixture['sale'], 'cash', 1000, 'KES', $fixture['user'], 'paid-twice');
                self::fail('A second full allocation should fail.');
            } catch (RuntimeException) {
                self::assertSame(1, PaymentAllocation::query()->count());
            }
            $this->expectException(RuntimeException::class);
            $payments->initiate($fixture['sale'], 'mpesa', 1000, 'KES', $fixture['user'], 'paid-once');
        });

        $other = Tenant::query()->create(['name' => 'Other Tenant', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $this->app->instance(SalePaymentLookup::class, new class($fixture) implements SalePaymentLookup
        {
            public function __construct(private array $fixture) {}

            public function finalized(string $saleId): PayableSale
            {
                return new PayableSale($saleId, $this->fixture['tenant'], 1000, 'KES');
            }
        });
        $this->app->forgetScopedInstances();
        $this->inTenant((string) $other->id, function (PaymentProcessor $payments) use ($fixture): void {
            $this->expectException(RuntimeException::class);
            $payments->initiate($fixture['sale'], 'cash', 1000, 'KES', $fixture['user'], 'cross-tenant');
        });
    }

    public function test_secret_like_metadata_and_illegal_mutations_are_rejected(): void
    {
        $fixture = $this->fixture('immutable', 100, 'KES');
        $this->bindSale($fixture);
        $this->inTenant($fixture['tenant'], function (PaymentProcessor $payments) use ($fixture): void {
            try {
                $payments->initiate($fixture['sale'], 'mpesa', 100, 'KES', $fixture['user'], 'unsafe', ['access_token' => 'secret']);
                self::fail('Secret-like metadata should fail.');
            } catch (InvalidArgumentException) {
                self::assertSame(0, PaymentAttempt::query()->count());
            }
            $result = $payments->initiate($fixture['sale'], 'cash', 100, 'KES', $fixture['user'], 'immutable');
            self::assertSame([], PaymentAttempt::query()->findOrFail($result->attemptId)->provider_metadata);
            $this->expectException(\Throwable::class);
            PaymentAllocation::query()->firstOrFail()->update(['amount_minor' => 1]);
        });
    }

    /** @return array{tenant:string,user:string,sale:string,gross:int,currency:string} */
    private function fixture(string $suffix, int $gross, string $currency): array
    {
        $tenant = Tenant::query()->create(['name' => "Tenant {$suffix}", 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $company = Company::query()->create(['tenant_id' => $tenant->id, 'name' => "Company {$suffix}", 'status' => 'active']);
        $branch = Branch::query()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => "BR-{$suffix}", 'name' => "Branch {$suffix}", 'status' => 'active']);
        $warehouse = Warehouse::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'code' => "WH-{$suffix}", 'name' => "Warehouse {$suffix}", 'status' => 'active']);
        $register = Register::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'code' => "POS-{$suffix}", 'name' => "Register {$suffix}", 'status' => 'active']);
        $user = User::factory()->create();
        $shiftId = (string) Str::uuid();
        DB::table('shifts')->insert(['id' => $shiftId, 'tenant_id' => $tenant->id, 'register_id' => $register->id, 'opening_user_id' => $user->id, 'status' => 'open', 'currency' => $currency, 'opening_float_minor' => 0, 'expected_cash_minor' => 0, 'opened_at' => now(), 'idempotency_key' => "shift-{$suffix}", 'created_at' => now(), 'updated_at' => now()]);
        $sale = Sale::query()->create(['tenant_id' => $tenant->id, 'shift_id' => $shiftId, 'register_id' => $register->id, 'warehouse_id' => $warehouse->id, 'actor_user_id' => $user->id, 'status' => 'finalized', 'currency_code' => $currency, 'net_minor' => $gross, 'tax_minor' => 0, 'gross_minor' => $gross, 'idempotency_key' => "sale-{$suffix}", 'command_fingerprint' => hash('sha256', $suffix), 'finalized_at' => now()]);

        return ['tenant' => (string) $tenant->id, 'user' => (string) $user->id, 'sale' => (string) $sale->id, 'gross' => $gross, 'currency' => $currency];
    }

    private function bindSale(array $fixture): void
    {
        $this->app->instance(SalePaymentLookup::class, new class($fixture) implements SalePaymentLookup
        {
            public function __construct(private array $fixture) {}

            public function finalized(string $saleId): PayableSale
            {
                return new PayableSale($saleId, $this->fixture['tenant'], $this->fixture['gross'], $this->fixture['currency']);
            }
        });
    }

    private function inTenant(string $tenantId, callable $operation): mixed
    {
        return $this->app->make(TenantScope::class)->run(TenantId::fromString($tenantId), fn () => $operation($this->app->make(PaymentProcessor::class)));
    }
}
