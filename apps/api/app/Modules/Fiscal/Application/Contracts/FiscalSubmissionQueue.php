<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Application\Contracts;

use App\Modules\Fiscal\Application\Data\FiscalInvoice;
use App\Modules\Fiscal\Application\Data\FiscalSubmission;

interface FiscalSubmissionQueue
{
    public function enqueue(FiscalInvoice $invoice): FiscalSubmission;

    public function findForSale(string $saleId): ?FiscalSubmission;
}
