<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Application\Data;

use InvalidArgumentException;

final readonly class FiscalGatewayResult
{
    public function __construct(
        public string $status,
        public ?string $providerReference = null,
        public ?string $resultCode = null,
        public ?string $errorMessage = null,
    ) {
        if (! in_array($this->status, ['submitted', 'retry_pending', 'rejected'], true)) {
            throw new InvalidArgumentException('Fiscal gateway status is invalid.');
        }
        if ($this->providerReference !== null && strlen($this->providerReference) > 128) {
            throw new InvalidArgumentException('Fiscal provider reference is too long.');
        }
        if ($this->resultCode !== null && strlen($this->resultCode) > 64) {
            throw new InvalidArgumentException('Fiscal result code is too long.');
        }
    }
}
