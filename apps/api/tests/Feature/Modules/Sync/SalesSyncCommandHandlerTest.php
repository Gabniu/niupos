<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sync;

use App\Modules\Sales\Application\Contracts\SalesCheckout;
use App\Modules\Sales\Application\FinalizedSale;
use App\Modules\Sync\Application\Data\SyncCommandEnvelope;
use App\Modules\Sync\Application\SalesSyncCommandHandler;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

final class SalesSyncCommandHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_sale_uses_authenticated_actor_device_register_and_command_idempotency_key(): void
    {
        $tenantId = '01989f8e-7a42-7b41-8fc0-87e9b48e813e';
        $device = (object) ['tenant_id' => $tenantId, 'register_id' => '01989f8e-1111-7111-8111-111111111111'];
        $query = Mockery::mock();
        $query->shouldReceive('where')->times(3)->andReturnSelf();
        $query->shouldReceive('first')->once()->andReturn($device);
        DB::shouldReceive('table')->once()->with('devices')->andReturn($query);
        $this->app->instance(SalesCheckout::class, $sales = Mockery::mock(SalesCheckout::class));
        $sales->shouldReceive('finalize')->once()->withArgs(function (...$args): bool {
            return $args[0] === '01989f8e-1111-7111-8111-111111111111'
                && $args[1] === '01989f8e-3333-7333-8333-333333333333'
                && $args[6] === '01989f8e-4444-7444-8444-444444444444';
        })->andReturn(new FinalizedSale('01989f8e-5555-7555-8555-555555555555', 'shift', 'register', 'KES', 100, 16, 116, 1, new DateTimeImmutable));
        Auth::shouldReceive('id')->andReturn('01989f8e-3333-7333-8333-333333333333');

        $handler = $this->app->make(SalesSyncCommandHandler::class);
        $result = $handler->handle($tenantId, '01989f8e-2222-7222-8222-222222222222', new SyncCommandEnvelope(
            '1', '01989f8e-4444-7444-8444-444444444444', 'sales.finalize.v1', '2026-08-08T10:00:00+00:00', [
                'warehouse_id' => '01989f8e-6666-7666-8666-666666666666',
                'price_book_id' => '01989f8e-7777-7777-8777-777777777777',
                'currency_code' => 'KES',
                'lines' => [['variant_id' => '01989f8e-8888-7888-8888-888888888888', 'quantity' => 1]],
            ],
        ));

        self::assertSame('applied', $result->status);
        self::assertSame('01989f8e-5555-7555-8555-555555555555', $result->evidence['saleId']);
    }

    public function test_unknown_type_is_explicitly_rejected_without_domain_side_effects(): void
    {
        $handler = new SalesSyncCommandHandler(Mockery::mock(SalesCheckout::class));
        $result = $handler->handle('tenant', 'device', new SyncCommandEnvelope('1', '01989f8e-4444-7444-8444-444444444444', 'payments.capture.v1', '2026-08-08T10:00:00+00:00', []));

        self::assertSame('rejected', $result->status);
        self::assertSame('unsupported_command_type', $result->code);
    }
}
