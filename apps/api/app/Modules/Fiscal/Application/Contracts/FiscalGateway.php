<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Application\Contracts;

use App\Modules\Fiscal\Application\Data\FiscalGatewayResult;
use App\Modules\Fiscal\Application\Data\FiscalInvoice;

interface FiscalGateway
{
    public function submit(FiscalInvoice $invoice): FiscalGatewayResult;
}
