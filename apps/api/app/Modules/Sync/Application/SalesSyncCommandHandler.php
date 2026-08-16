<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application;

use App\Modules\Sales\Application\Contracts\SalesCheckout;
use App\Modules\Sync\Application\Contracts\SyncCommandHandler;
use App\Modules\Sync\Application\Data\SyncCommandEnvelope;
use App\Modules\Sync\Application\Data\SyncCommandOutcome;
use DateTimeImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class SalesSyncCommandHandler implements SyncCommandHandler
{
    public function __construct(
        private SalesCheckout $sales,
    ) {}

    public function handle(string $tenantId, string $deviceId, SyncCommandEnvelope $command): SyncCommandOutcome
    {
        if ($command->type !== 'sales.finalize.v1') {
            return SyncCommandOutcome::rejected('unsupported_command_type', 'No handler is registered for this command type.');
        }

        try {
            $device = DB::table('devices')->where('id', $deviceId)->where('tenant_id', $tenantId)->where('status', 'active')->first();
            if ($device === null) {
                return SyncCommandOutcome::rejected('device_unavailable', 'The synchronization device is unavailable.');
            }

            $payload = $command->payload;
            foreach (['warehouse_id', 'price_book_id', 'currency_code', 'lines'] as $required) {
                if (! array_key_exists($required, $payload)) {
                    return SyncCommandOutcome::rejected('invalid_sales_command', 'The offline sale command is invalid.');
                }
            }
            if (! is_array($payload['lines']) || $payload['lines'] === []) {
                return SyncCommandOutcome::rejected('invalid_sales_command', 'The offline sale command is invalid.');
            }

            $actor = Auth::id();
            if (! is_string($actor) || $actor === '') {
                $user = function_exists('request') ? request()->user() : null;
                $actor = is_object($user) && method_exists($user, 'getAuthIdentifier')
                    ? (string) $user->getAuthIdentifier()
                    : '';
            }
            if ($actor === '') {
                return SyncCommandOutcome::rejected('actor_unavailable', 'The authenticated operator is unavailable.');
            }

            $sale = $this->sales->finalize(
                (string) $device->register_id,
                $actor,
                (string) $payload['warehouse_id'],
                (string) $payload['price_book_id'],
                (string) $payload['currency_code'],
                $payload['lines'],
                $command->commandId,
                new DateTimeImmutable($command->occurredAt),
            );

            return new SyncCommandOutcome('applied', evidence: [
                'saleId' => $sale->saleId,
                'registerId' => $sale->registerId,
                'grossMinor' => $sale->grossMinor,
                'currencyCode' => $sale->currencyCode,
            ]);
        } catch (Throwable) {
            return SyncCommandOutcome::rejected('sales_command_rejected', 'The offline sale command was rejected.');
        }
    }
}
