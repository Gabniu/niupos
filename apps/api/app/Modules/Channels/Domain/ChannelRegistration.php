<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ChannelRegistration extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'channel', 'audience', 'environment', 'display_name',
        'client_id', 'status', 'configuration', 'redirect_uris',
        'idempotency_key', 'idempotency_fingerprint',
    ];

    protected function casts(): array
    {
        return ['configuration' => 'array', 'redirect_uris' => 'array'];
    }
}
