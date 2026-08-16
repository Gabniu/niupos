<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain;

enum PricingStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
