<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Application\Contracts\WorkspacePreferencesReader;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseWorkspacePreferencesReader implements WorkspacePreferencesReader
{
    public function __construct(private TenantContext $context) {}

    public function reportingTimezone(): string
    {
        $timezone = DB::table('tenant_workspace_preferences')
            ->where('tenant_id', (string) $this->context->id())
            ->value('reporting_timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }
}
