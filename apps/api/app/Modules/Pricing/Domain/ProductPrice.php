<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ProductPrice extends Model
{
    use HasUuids;

    protected $table = 'pricing_product_prices';

    protected $fillable = ['tenant_id', 'price_book_id', 'product_variant_id', 'tax_category_id', 'amount_minor', 'effective_from', 'effective_until'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'effective_from' => 'immutable_datetime', 'effective_until' => 'immutable_datetime'];
    }
}
