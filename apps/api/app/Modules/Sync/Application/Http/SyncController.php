<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Http;

use App\Modules\Sync\Application\Contracts\SyncBootstrap;
use App\Modules\Sync\Application\Contracts\SyncProtocol;
use App\Modules\Sync\Application\Data\SyncChange;
use App\Modules\Sync\Application\Data\SyncCommandEnvelope;
use App\Modules\Sync\Application\Data\SyncCommandReceipt;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final readonly class SyncController
{
    public function __construct(private SyncProtocol $sync, private SyncBootstrap $bootstrap) {}

    public function bootstrap(Request $request): JsonResponse
    {
        $allowed = ['section', 'collection', 'after_id', 'limit', 'snapshot_cursor'];
        if (array_diff(array_keys($request->query()), $allowed) !== []) {
            return $this->failure(Response::HTTP_UNPROCESSABLE_ENTITY, 'SYNC_INVALID', 'The bootstrap request contains unsupported fields.');
        }
        $data = $request->validate([
            'section' => ['sometimes', 'in:catalogue,pricing'],
            'collection' => ['required_with:section', 'string', 'max:64'],
            'after_id' => ['sometimes', 'uuid'],
            'limit' => ['sometimes', 'integer', 'between:1,500'],
            'snapshot_cursor' => ['sometimes', 'integer', 'min:0'],
        ]);
        if (isset($data['section']) && ! isset($data['collection'])) {
            return $this->failure(Response::HTTP_UNPROCESSABLE_ENTITY, 'SYNC_INVALID', 'A bootstrap collection is required when paging.');
        }
        if ((isset($data['after_id']) || isset($data['limit']) || isset($data['snapshot_cursor'])) && ! isset($data['section'])) {
            return $this->failure(Response::HTTP_UNPROCESSABLE_ENTITY, 'SYNC_INVALID', 'A bootstrap section is required when paging.');
        }
        $page = isset($data['section']) ? $data : null;
        $device = $this->deviceHeader($request);
        if ($device instanceof JsonResponse) {
            return $device;
        }
        try {
            return new JsonResponse($this->bootstrap->snapshot($device, $page));
        } catch (DomainException) {
            return $this->failure(Response::HTTP_NOT_FOUND, 'SYNC_DEVICE_UNAVAILABLE', 'The synchronization device is unavailable.');
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'SYNC_BOOTSTRAP_CHANGED') {
                return $this->failure(Response::HTTP_CONFLICT, 'SYNC_BOOTSTRAP_CHANGED', 'The catalogue changed while it was being transferred. Restart the bootstrap.');
            }
            throw $exception;
        }
    }

    public function changes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'after_cursor' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'between:1,500'],
        ]);
        $device = $this->deviceHeader($request);
        if ($device instanceof JsonResponse) {
            return $device;
        }

        try {
            $page = $this->sync->pull($device, (int) ($data['after_cursor'] ?? 0), (int) ($data['limit'] ?? 100));
        } catch (InvalidArgumentException) {
            return $this->failure(Response::HTTP_UNPROCESSABLE_ENTITY, 'SYNC_INVALID', 'The synchronization request is invalid.');
        } catch (DomainException) {
            return $this->failure(Response::HTTP_NOT_FOUND, 'SYNC_DEVICE_UNAVAILABLE', 'The synchronization device is unavailable.');
        }

        return new JsonResponse([
            'version' => $page->version,
            'cursor' => $page->cursor,
            'changes' => array_map(fn (SyncChange $change): array => [
                'cursor' => $change->cursor,
                'entityType' => $change->entityType,
                'entityId' => $change->entityId,
                'operation' => $change->operation,
                'payload' => $change->payload,
                'occurredAt' => $change->occurredAt,
            ], $page->changes),
            'hasMore' => $page->hasMore,
        ]);
    }

    public function commands(Request $request): JsonResponse
    {
        $data = $request->validate([
            'version' => ['required', 'in:1'],
            'commandId' => ['required', 'uuid'],
            'type' => ['required', 'string', 'max:128', 'regex:/\S/'],
            'occurredAt' => ['required', 'string', 'max:64', 'date'],
            'payload' => ['required', 'array'],
        ]);
        if (array_diff(array_keys($request->all()), ['version', 'commandId', 'type', 'occurredAt', 'payload']) !== []) {
            return $this->failure(Response::HTTP_UNPROCESSABLE_ENTITY, 'SYNC_INVALID', 'The synchronization command contains unsupported fields.');
        }
        $device = $this->deviceHeader($request);
        if ($device instanceof JsonResponse) {
            return $device;
        }

        try {
            $receipt = $this->sync->submit($device, new SyncCommandEnvelope(
                (string) $data['version'], $data['commandId'], trim($data['type']), $data['occurredAt'], $data['payload'],
            ));
        } catch (InvalidArgumentException) {
            return $this->failure(Response::HTTP_UNPROCESSABLE_ENTITY, 'SYNC_INVALID', 'The synchronization command is invalid.');
        } catch (DomainException) {
            return $this->failure(Response::HTTP_NOT_FOUND, 'SYNC_DEVICE_UNAVAILABLE', 'The synchronization device is unavailable.');
        } catch (RuntimeException) {
            return $this->failure(Response::HTTP_CONFLICT, 'SYNC_COMMAND_CONFLICT', 'The synchronization command conflicts with an existing command.');
        }

        return new JsonResponse(['data' => $this->receipt($receipt)], Response::HTTP_OK);
    }

    private function deviceHeader(Request $request): string|JsonResponse
    {
        $device = $request->header('X-Device-Id');
        if (! is_string($device) || strlen($device) > 36 || ! Str::isUuid($device)) {
            return $this->failure(Response::HTTP_UNPROCESSABLE_ENTITY, 'SYNC_DEVICE_INVALID', 'A valid X-Device-Id header is required.');
        }

        return strtolower($device);
    }

    /** @return array{commandId:string,status:string,attempts:int,resultCode:?string,resultMessage:?string} */
    private function receipt(SyncCommandReceipt $receipt): array
    {
        return [
            'commandId' => $receipt->commandId,
            'status' => $receipt->status,
            'attempts' => $receipt->attempts,
            'resultCode' => $receipt->resultCode,
            'resultMessage' => $receipt->resultMessage,
        ];
    }

    private function failure(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
