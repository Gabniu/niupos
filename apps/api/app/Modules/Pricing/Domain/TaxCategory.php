<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class TaxCategory extends Model
{
    use HasUuids;

    protected $table = 'pricing_tax_categories';

    protected $fillable = ['tenant_id', 'code', 'rate_basis_points', 'is_inclusive', 'status'];

    protected function casts(): array
    {
        return ['rate_basis_points' => 'integer', 'is_inclusive' => 'boolean'];
    }
}
