<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Console;

use App\Modules\Identity\Application\Contracts\TenantOwnerBootstrap;
use App\Modules\Identity\Domain\User;
use Illuminate\Console\Command;
use Throwable;

final class BootstrapTenantOwnerCommand extends Command
{
    protected $signature = 'nova:tenant:bootstrap-owner
        {tenant : Exact tenant UUID}
        {user : Exact owner user UUID}
        {--operator= : Operator principal or approved change reference}
        {--force : Skip interactive confirmation}';

    protected $description = 'Bootstrap the first owner of an otherwise membership-empty tenant';

    public function handle(TenantOwnerBootstrap $bootstrap): int
    {
        $tenantId = (string) $this->argument('tenant');
        $userId = (string) $this->argument('user');
        $operator = trim((string) $this->option('operator'));
        if ($operator === '') {
            $this->error('The --operator reference is required.');
            return self::INVALID;
        }
        $owner = User::query()->find($userId);
        if (! $owner instanceof User) {
            $this->error('The exact owner user UUID was not found.');
            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm("Bootstrap user {$userId} as the first owner of tenant {$tenantId}?")) {
            $this->warn('Bootstrap cancelled.');
            return self::SUCCESS;
        }
        try {
            $membership = $bootstrap->bootstrap($tenantId, $owner, $operator);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
        $this->info('Tenant owner bootstrapped. Membership: '.$membership->getKey());
        return self::SUCCESS;
    }
}
