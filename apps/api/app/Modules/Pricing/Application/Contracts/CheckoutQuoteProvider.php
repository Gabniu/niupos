<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Contracts;

use App\Modules\Pricing\Application\CheckoutLineQuote;
use DateTimeInterface;

interface CheckoutQuoteProvider
{
    public function quote(
        string $priceBookId,
        string $variantId,
        int $quantity,
        string $currencyCode,
        DateTimeInterface $at,
    ): CheckoutLineQuote;
}
