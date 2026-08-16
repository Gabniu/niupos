<?php

declare(strict_types=1);

namespace App\Modules\Register\Application\Contracts;

use App\Modules\Register\Application\IssuedDeviceEnrollment;
use App\Modules\Register\Domain\Device;
use App\Modules\Register\Domain\Register;
use DateTimeInterface;

interface RegisterDeviceManager
{
    public function createRegister(string $branchId, string $code, string $name): Register;

    public function issueDeviceEnrollment(string $registerId, string $displayName, DateTimeInterface $expiresAt): IssuedDeviceEnrollment;

    public function consumeDeviceEnrollment(string $token): Device;

    public function resolveActiveDevice(string $publicId): ?Device;
}
