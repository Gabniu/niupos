<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class OnboardingProvisioningAction extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'run_id', 'sequence', 'code', 'status',
        'requires_approval', 'reversible', 'details', 'result', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'requires_approval' => 'boolean',
            'reversible' => 'boolean',
            'details' => 'array',
            'result' => 'array',
        ];
    }
}
