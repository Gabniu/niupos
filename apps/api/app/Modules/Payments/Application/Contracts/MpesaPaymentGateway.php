<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Contracts;

use App\Modules\Payments\Application\Data\MpesaGatewayRequest;
use App\Modules\Payments\Application\Data\MpesaGatewayResult;

interface MpesaPaymentGateway
{
    public function request(MpesaGatewayRequest $request): MpesaGatewayResult;
}
