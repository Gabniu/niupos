<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Application\Contracts;

use App\Modules\Fiscal\Application\Data\FiscalSubmissionSummary;

interface FiscalSubmissionReader
{
    public function summary(): FiscalSubmissionSummary;
}
