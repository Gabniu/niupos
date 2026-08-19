<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Application\Data;

final readonly class FiscalSubmission
{
    public function __construct(
        public string $id,
        public string $saleId,
        public string $profile,
        public string $status,
        public int $attempts,
        public ?string $providerReference,
        public ?string $lastResultCode,
    ) {}
}
