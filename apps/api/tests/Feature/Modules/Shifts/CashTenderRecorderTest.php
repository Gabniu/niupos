<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Shifts;

use App\Modules\Identity\Domain\User;
use App\Modules\Register\Domain\Register;
use App\Modules\Shifts\Application\Contracts\CashTenderRecorder;
use App\Modules\Shifts\Domain\CashMovement;
use App\Modules\Shifts\Domain\Shift;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Branch;
use App\Modules\Tenancy\Domain\Company;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class CashTenderRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_tender_updates_expected_drawer_once_and_conflicting_replay_fails(): void
    {
        [$tenantId, $shiftId, $userId] = $this->fixture();

        $this->app->make(TenantScope::class)->run(TenantId::fromString($tenantId), function () use ($shiftId, $userId): void {
            $recorder = $this->app->make(CashTenderRecorder::class);
            $first = $recorder->record($shiftId, (string) Str::uuid(), $userId, 1160, 'KES', 'cash-sale-1');
            $replay = $recorder->record($shiftId, $first->saleId, $userId, 1160, 'KES', 'cash-sale-1');
            self::assertSame($first->movementId, $replay->movementId);
            self::assertSame(1160, (int) Shift::query()->findOrFail($shiftId)->expected_cash_minor);
            self::assertSame(1, CashMovement::query()->where('type', 'sale_cash')->count());

            try {
                $recorder->record($shiftId, $first->saleId, $userId, 1200, 'KES', 'cash-sale-1');
                self::fail('Conflicting replay should fail.');
            } catch (RuntimeException) {
                self::assertSame(1160, (int) Shift::query()->findOrFail($shiftId)->expected_cash_minor);
            }
        });
    }

    /** @return array{string,string,string} */
    private function fixture(): array
    {
        $tenant = Tenant::query()->create(['name' => 'Tender Tenant', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $company = Company::query()->create(['tenant_id' => $tenant->id, 'name' => 'Tender Company', 'status' => 'active']);
        $branch = Branch::query()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'TENDER-BR', 'name' => 'Tender Branch', 'status' => 'active']);
        $register = Register::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'code' => 'TENDER-POS', 'name' => 'Tender Register', 'status' => 'active']);
        $user = User::factory()->create();
        DB::table('tenant_memberships')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $shift = Shift::query()->create(['tenant_id' => $tenant->id, 'register_id' => $register->id, 'opening_user_id' => $user->id, 'status' => 'open', 'currency' => 'KES', 'opening_float_minor' => 0, 'expected_cash_minor' => 0, 'opened_at' => now(), 'idempotency_key' => 'tender-shift']);

        return [(string) $tenant->id, (string) $shift->id, (string) $user->id];
    }
}
