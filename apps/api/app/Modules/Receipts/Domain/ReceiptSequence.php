<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ReceiptSequence extends Model
{
    use HasUuids;

    protected $guarded = [];
}
