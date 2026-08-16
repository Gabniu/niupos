<?php

declare(strict_types=1);

namespace App\Modules\Shifts\Application\Http;

use App\Modules\Shifts\Application\Contracts\ShiftCashManager;
use App\Modules\Shifts\Domain\Shift;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final readonly class ShiftOperationsController
{
    public function __construct(private TenantContext $context, private ShiftCashManager $shifts) {}

    public function current(Request $request): JsonResponse
    {
        $data = $request->validate(['register_id' => ['required', 'uuid']]);
        $shift = Shift::query()->where('tenant_id', (string) $this->context->id())->where('register_id', $data['register_id'])->where('status', 'open')->first();

        return new JsonResponse(['data' => $shift === null ? null : $this->view($shift)]);
    }

    public function open(Request $request): JsonResponse
    {
        $data = $request->validate([
            'register_id' => ['required', 'uuid'],
            'opening_float_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
        ]);
        $key = (string) $request->header('Idempotency-Key');
        if (trim($key) === '' || strlen($key) > 128) {
            return new JsonResponse(['error' => ['code' => 'SHIFT_INVALID', 'message' => 'A valid Idempotency-Key header is required.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $shift = $this->shifts->openShift($data['register_id'], (string) $request->user()->getAuthIdentifier(), $data['opening_float_minor'], strtoupper($data['currency']), $key);

        return new JsonResponse(['data' => $this->view($shift)], Response::HTTP_CREATED);
    }

    public function movement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shift_id' => ['required', 'uuid'],
            'type' => ['required', Rule::in(['pay_in', 'pay_out'])],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255', 'regex:/\S/'],
        ]);
        $key = (string) $request->header('Idempotency-Key');
        if (trim($key) === '' || strlen($key) > 128) {
            return new JsonResponse(['error' => ['code' => 'SHIFT_INVALID', 'message' => 'A valid Idempotency-Key header is required.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $movement = $this->shifts->recordCashMovement($data['shift_id'], $data['type'], $data['amount_minor'], $data['reason'], (string) $request->user()->getAuthIdentifier(), $key);

        return new JsonResponse(['data' => ['id' => (string) $movement->getKey(), 'shift_id' => (string) $movement->shift_id, 'type' => $movement->type, 'amount_minor' => (int) $movement->amount_minor, 'currency' => (string) $movement->currency, 'reason' => $movement->reason]], Response::HTTP_CREATED);
    }

    public function close(Request $request, string $shift): JsonResponse
    {
        $data = $request->validate(['counted_cash_minor' => ['required', 'integer', 'min:0']]);
        $closed = $this->shifts->closeShift($shift, (string) $request->user()->getAuthIdentifier(), $data['counted_cash_minor']);

        return new JsonResponse(['data' => $this->view($closed)]);
    }

    /** @return array<string, mixed> */
    private function view(Shift $shift): array
    {
        return ['id' => (string) $shift->getKey(), 'register_id' => (string) $shift->register_id, 'status' => (string) $shift->status, 'currency' => (string) $shift->currency, 'opening_float_minor' => (int) $shift->opening_float_minor, 'expected_cash_minor' => (int) $shift->expected_cash_minor, 'opened_at' => $shift->opened_at?->format(DATE_ATOM), 'closed_at' => $shift->closed_at?->format(DATE_ATOM), 'variance_minor' => $shift->variance_minor === null ? null : (int) $shift->variance_minor];
    }
}
