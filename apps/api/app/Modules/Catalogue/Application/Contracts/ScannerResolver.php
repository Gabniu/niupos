<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Application\Contracts;

use App\Modules\Catalogue\Application\Scanner\ScannerInputMode;
use App\Modules\Catalogue\Application\Scanner\ScanResult;

interface ScannerResolver
{
    public function resolve(string $value, ScannerInputMode $mode): ScanResult;
}
