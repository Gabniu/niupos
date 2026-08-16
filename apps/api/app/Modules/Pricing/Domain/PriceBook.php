<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class PriceBook extends Model
{
    use HasUuids;

    protected $table = 'pricing_price_books';

    protected $fillable = ['tenant_id', 'name', 'currency_code', 'status'];
}
