<?php

declare(strict_types=1);

namespace App\Modules\Register\Application;

use App\Modules\Register\Domain\Device;

final readonly class IssuedDeviceEnrollment
{
    public function __construct(public Device $device, public string $token) {}
}
