<?php

declare(strict_types=1);

namespace App\Modules\Register\Application;

use App\Modules\Register\Application\Contracts\RegisterDeviceManager;
use App\Modules\Register\Domain\Device;
use App\Modules\Register\Domain\DeviceStatus;
use App\Modules\Register\Domain\Register;
use App\Modules\Tenancy\Application\TenantContext;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class DatabaseRegisterDeviceManager implements RegisterDeviceManager
{
    public function __construct(private TenantContext $tenantContext) {}

    public function createRegister(string $branchId, string $code, string $name): Register
    {
        $tenantId = (string) $this->tenantContext->id();
        $code = trim($code);
        $name = trim($name);

        if ($code === '' || $name === '') {
            throw new InvalidArgumentException('Register code and name cannot be empty.');
        }

        if (! DB::table('branches')->where('tenant_id', $tenantId)->where('id', $branchId)->exists()) {
            throw new DomainException('Branch must belong to the current tenant.');
        }

        return Register::query()->create([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'code' => $code,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    public function issueDeviceEnrollment(string $registerId, string $displayName, DateTimeInterface $expiresAt): IssuedDeviceEnrollment
    {
        $tenantId = (string) $this->tenantContext->id();
        $displayName = trim($displayName);

        if ($displayName === '') {
            throw new InvalidArgumentException('Device display name cannot be empty.');
        }
        if ($expiresAt->getTimestamp() <= now()->getTimestamp()) {
            throw new InvalidArgumentException('Enrollment expiry must be in the future.');
        }
        if (! Register::query()->where('tenant_id', $tenantId)->where('status', 'active')->whereKey($registerId)->exists()) {
            throw new DomainException('Register must be active and belong to the current tenant.');
        }

        $token = self::newToken();
        $device = Device::query()->create([
            'tenant_id' => $tenantId,
            'register_id' => $registerId,
            'public_id' => (string) Str::uuid(),
            'display_name' => $displayName,
            'status' => DeviceStatus::PendingEnrollment->value,
            'enrollment_token_digest' => self::digest($token),
            'enrollment_expires_at' => $expiresAt,
        ]);

        return new IssuedDeviceEnrollment($device, $token);
    }

    public function consumeDeviceEnrollment(string $token): Device
    {
        $tenantId = (string) $this->tenantContext->id();
        $digest = self::digest($token);

        return DB::transaction(function () use ($tenantId, $digest): Device {
            $device = Device::query()
                ->where('tenant_id', $tenantId)
                ->where('enrollment_token_digest', $digest)
                ->lockForUpdate()
                ->first();

            if ($device === null || $device->enrollment_consumed_at !== null || $device->enrollment_expires_at === null || $device->enrollment_expires_at->isPast()) {
                throw new DomainException('Device enrollment token is invalid, expired, or already consumed.');
            }

            $device->forceFill([
                'status' => DeviceStatus::Active->value,
                'enrollment_token_digest' => null,
                'enrollment_consumed_at' => now(),
            ])->save();

            return $device->refresh();
        });
    }

    public function resolveActiveDevice(string $publicId): ?Device
    {
        $tenantId = (string) $this->tenantContext->id();

        return Device::query()
            ->where('tenant_id', $tenantId)
            ->where('public_id', $publicId)
            ->where('status', DeviceStatus::Active->value)
            ->whereNotNull('enrollment_consumed_at')
            ->first();
    }

    private static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private static function digest(string $token): string
    {
        if ($token === '') {
            throw new InvalidArgumentException('Enrollment token cannot be empty.');
        }

        return hash('sha256', $token);
    }
}
