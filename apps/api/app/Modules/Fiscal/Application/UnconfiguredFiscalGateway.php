<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Application;

use App\Modules\Fiscal\Application\Contracts\FiscalGateway;
use App\Modules\Fiscal\Application\Data\FiscalGatewayResult;
use App\Modules\Fiscal\Application\Data\FiscalInvoice;

final class UnconfiguredFiscalGateway implements FiscalGateway
{
    public function submit(FiscalInvoice $invoice): FiscalGatewayResult
    {
        return new FiscalGatewayResult('retry_pending', resultCode: 'gateway_unconfigured', errorMessage: 'Fiscal provider is not configured.');
    }
}
