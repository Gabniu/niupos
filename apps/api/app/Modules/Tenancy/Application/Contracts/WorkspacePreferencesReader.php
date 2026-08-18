<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\Contracts;

interface WorkspacePreferencesReader
{
    public function reportingTimezone(): string;
}
