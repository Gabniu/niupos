<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sales;

use App\Application\Contracts\CashSaleCompletion;
use App\Application\Data\CompletedCashSale;
use App\Modules\Identity\Application\Contracts\ApiSessionManager;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;
use App\Modules\Sales\Application\Contracts\SalesCheckout;
use App\Modules\Sales\Application\FinalizedSale;
use App\Modules\Tenancy\Domain\Tenant;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SalesHttpTransportTest extends TestCase
{
    use RefreshDatabase;

    private const REGISTER_ID = '11111111-1111-4111-8111-111111111111';

    private const WAREHOUSE_ID = '22222222-2222-4222-8222-222222222222';

    private const PRICE_BOOK_ID = '33333333-3333-4333-8333-333333333333';

    private const VARIANT_ID = '44444444-4444-4444-8444-444444444444';

    private const SALE_ID = '55555555-5555-4555-8555-555555555555';

    #[Test]
    public function middleware_admits_authentication_then_tenant_then_permission(): void
    {
        [$tenantId, , $token] = $this->identityFixture(withPermission: false);

        $this->withHeader('X-Tenant-ID', $tenantId)->postJson('/api/v1/sales/finalize', [])
            ->assertUnauthorized();
        $this->flushHeaders();
        $this->withToken($token)->postJson('/api/v1/sales/finalize', [])
            ->assertBadRequest();
        $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
            ->postJson('/api/v1/sales/finalize', [])
            ->assertForbidden();
    }

    #[Test]
    public function finalize_validates_payload_and_idempotency_header_before_invocation(): void
    {
        [$tenantId, , $token] = $this->identityFixture();
        $this->mock(SalesCheckout::class)->shouldNotReceive('finalize');

        $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
            ->postJson('/api/v1/sales/finalize', [
                'register_id' => 'bad', 'warehouse_id' => 'bad', 'price_book_id' => 'bad',
                'currency_code' => 'kes', 'lines' => [], 'occurred_at' => 'tomorrow',
            ])->assertUnprocessable()->assertJsonValidationErrors([
                'register_id', 'warehouse_id', 'price_book_id', 'currency_code', 'lines',
                'occurred_at', 'idempotency_key',
            ]);
    }

    #[Test]
    public function finalize_maps_valid_input_and_uses_authenticated_actor(): void
    {
        [$tenantId, $actor, $token] = $this->identityFixture();
        $result = $this->finalizedSale();
        $this->mock(SalesCheckout::class)->shouldReceive('finalize')->once()->withArgs(
            fn (string $registerId, string $actorId, string $warehouseId, string $priceBookId,
                string $currency, array $lines, string $key, DateTimeInterface $at): bool => $registerId === self::REGISTER_ID
                && $actorId === (string) $actor->getKey()
                && $warehouseId === self::WAREHOUSE_ID
                && $priceBookId === self::PRICE_BOOK_ID
                && $currency === 'KES'
                && $lines === [['variant_id' => self::VARIANT_ID, 'quantity' => 2]]
                && $key === 'sale-key-1'
                && $at->format(DATE_ATOM) === '2026-08-08T10:15:00+03:00',
        )->andReturn($result);

        $this->withToken($token)->withHeaders(['X-Tenant-ID' => $tenantId, 'Idempotency-Key' => 'sale-key-1'])
            ->postJson('/api/v1/sales/finalize', [
                'actor_user_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                'register_id' => self::REGISTER_ID, 'warehouse_id' => self::WAREHOUSE_ID,
                'price_book_id' => self::PRICE_BOOK_ID, 'currency_code' => 'KES',
                'lines' => [['variant_id' => self::VARIANT_ID, 'quantity' => 2]],
                'occurred_at' => '2026-08-08T10:15:00+03:00',
            ])->assertCreated()->assertExactJson(['data' => [
                'sale_id' => self::SALE_ID, 'shift_id' => $result->shiftId,
                'register_id' => self::REGISTER_ID, 'currency_code' => 'KES',
                'net_minor' => 1000, 'tax_minor' => 160, 'gross_minor' => 1160,
                'line_count' => 1, 'finalized_at' => '2026-08-08T10:15:00+03:00',
            ]]);
    }

    #[Test]
    public function cash_completion_maps_contract_and_returns_stable_replay_representation(): void
    {
        [$tenantId, $actor, $token] = $this->identityFixture();
        $completed = new CompletedCashSale(self::SALE_ID, '66666666-6666-4666-8666-666666666666',
            '77777777-7777-4777-8777-777777777777', '88888888-8888-4888-8888-888888888888', 42, 1160, 'KES');
        $this->mock(CashSaleCompletion::class)->shouldReceive('complete')->twice()->withArgs(
            fn (string $saleId, string $actorId, string $key, DateTimeInterface $at): bool => $saleId === self::SALE_ID && $actorId === (string) $actor->getKey()
                && $key === 'cash-key-1' && $at->format(DATE_ATOM) === '2026-08-08T10:16:00+03:00',
        )->andReturn($completed);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->withToken($token)->withHeaders(['X-Tenant-ID' => $tenantId, 'Idempotency-Key' => 'cash-key-1'])
                ->postJson('/api/v1/sales/'.self::SALE_ID.'/cash-complete', [
                    'actor_user_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                    'completed_at' => '2026-08-08T10:16:00+03:00',
                ])->assertCreated()->assertJsonPath('data.receipt_number', 42)
                ->assertJsonPath('data.amount_minor', 1160);
        }
    }

    #[Test]
    public function sales_transport_is_throttled_per_session(): void
    {
        [$tenantId, , $token] = $this->identityFixture();
        $this->mock(SalesCheckout::class)->shouldReceive('finalize')->times(60)->andReturn($this->finalizedSale());
        $headers = ['X-Tenant-ID' => $tenantId, 'Idempotency-Key' => 'rate-key'];
        $payload = $this->finalizePayload();

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/sales/finalize', $payload)->assertCreated();
        }
        $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/sales/finalize', $payload)
            ->assertTooManyRequests();
    }

    /** @return array{string, User, string} */
    private function identityFixture(bool $withPermission = true): array
    {
        $tenant = Tenant::query()->create(['name' => 'Sales HTTP '.bin2hex(random_bytes(3)), 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $actor = User::factory()->create();
        $role = Role::query()->create(['tenant_id' => $tenant->getKey(), 'name' => 'sales-http-'.bin2hex(random_bytes(3))]);
        DB::table('permissions')->insertOrIgnore(['id' => 'sales.checkout.create', 'description' => 'Finalize sales checkout']);
        if ($withPermission) {
            DB::table('role_permissions')->insert(['tenant_id' => $tenant->getKey(), 'role_id' => $role->getKey(), 'permission_id' => 'sales.checkout.create']);
        }
        TenantMembership::query()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $actor->getKey(), 'role_id' => $role->getKey(), 'status' => 'active']);

        return [(string) $tenant->getKey(), $actor, $this->app->make(ApiSessionManager::class)->issue($actor)->token];
    }

    /** @return array<string, mixed> */
    private function finalizePayload(): array
    {
        return ['register_id' => self::REGISTER_ID, 'warehouse_id' => self::WAREHOUSE_ID,
            'price_book_id' => self::PRICE_BOOK_ID, 'currency_code' => 'KES',
            'lines' => [['variant_id' => self::VARIANT_ID, 'quantity' => 2]],
            'occurred_at' => '2026-08-08T10:15:00+03:00'];
    }

    private function finalizedSale(): FinalizedSale
    {
        return new FinalizedSale(self::SALE_ID, '99999999-9999-4999-8999-999999999999', self::REGISTER_ID,
            'KES', 1000, 160, 1160, 1, new DateTimeImmutable('2026-08-08T10:15:00+03:00'));
    }
}
