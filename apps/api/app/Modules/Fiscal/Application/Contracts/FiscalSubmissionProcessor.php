<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Application\Contracts;

interface FiscalSubmissionProcessor
{
    public function processDue(int $limit = 50): int;
}
