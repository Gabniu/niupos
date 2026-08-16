<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Receipts;

use App\Modules\Identity\Application\Contracts\ApiSessionManager;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;
use App\Modules\Receipts\Domain\ReceiptDeliveryAttempt;
use App\Modules\Register\Domain\Register;
use App\Modules\Tenancy\Domain\Branch;
use App\Modules\Tenancy\Domain\Company;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReceiptHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_read_requires_auth_tenant_and_permission_and_returns_complete_ordered_snapshot(): void
    {
        $f = $this->fixture(['receipts.read']);
        $this->withHeader('X-Tenant-ID', $f['tenant'])->getJson("/api/v1/receipts/{$f['receipt']}")->assertUnauthorized();
        $this->flushHeaders();
        $this->withToken($f['token'])->getJson("/api/v1/receipts/{$f['receipt']}")->assertBadRequest();

        $this->withToken($f['token'])->withHeader('X-Tenant-ID', $f['tenant'])->getJson("/api/v1/receipts/{$f['receipt']}")
            ->assertOk()->assertJsonPath('data.id', $f['receipt'])->assertJsonPath('data.gross_minor', 11600)
            ->assertJsonPath('data.lines.0.line_number', 1)->assertJsonPath('data.lines.1.line_number', 2)
            ->assertJsonPath('data.lines.0.description', 'First snapshot');

        $without = $this->fixture([]);
        $this->withToken($without['token'])->withHeader('X-Tenant-ID', $without['tenant'])
            ->getJson("/api/v1/receipts/{$without['receipt']}")->assertForbidden();
    }

    public function test_cross_tenant_receipts_are_generic_missing_and_delivery_maps_only_safe_evidence(): void
    {
        $owner = $this->fixture(['receipts.read']);
        $other = $this->fixture(['receipts.read', 'receipts.delivery.record']);
        $this->withToken($other['token'])->withHeader('X-Tenant-ID', $other['tenant'])
            ->getJson("/api/v1/receipts/{$owner['receipt']}")->assertNotFound();
        $this->withToken($other['token'])->withHeader('X-Tenant-ID', $other['tenant'])
            ->postJson("/api/v1/receipts/{$owner['receipt']}/delivery-attempts", [
                'channel' => 'printer', 'outcome' => 'failed', 'attempted_at' => '2026-08-08T12:00:00+03:00', 'error_code' => 'PAPER_OUT',
            ])->assertNotFound();

        $this->withToken($other['token'])->withHeader('X-Tenant-ID', $other['tenant'])
            ->postJson("/api/v1/receipts/{$other['receipt']}/delivery-attempts", [
                'channel' => 'printer', 'outcome' => 'failed', 'attempted_at' => '2026-08-08T12:00:00+03:00', 'error_code' => 'PAPER_OUT',
            ])->assertCreated()->assertJsonPath('data.error_code', 'PAPER_OUT');
        self::assertSame('PAPER_OUT', ReceiptDeliveryAttempt::query()->where('tenant_id', $other['tenant'])->firstOrFail()->error_code);
    }

    public function test_delivery_validation_rejects_invalid_uuid_enums_secret_fields_and_error_mismatches(): void
    {
        $f = $this->fixture(['receipts.delivery.record']);
        $url = "/api/v1/receipts/{$f['receipt']}/delivery-attempts";
        $headers = ['Authorization' => 'Bearer '.$f['token'], 'X-Tenant-ID' => $f['tenant']];
        $this->postJson('/api/v1/receipts/not-a-uuid/delivery-attempts', [], $headers)->assertUnprocessable();
        $this->postJson($url, ['channel' => 'webhook', 'outcome' => 'sent', 'attempted_at' => 'never'], $headers)->assertUnprocessable();
        $this->postJson($url, ['channel' => 'email', 'outcome' => 'succeeded', 'attempted_at' => now()->toAtomString(), 'destination' => 'secret@example.test', 'body' => 'secret'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('payload');
        $this->postJson($url, ['channel' => 'sms', 'outcome' => 'failed', 'attempted_at' => now()->toAtomString()], $headers)->assertUnprocessable();
        $this->postJson($url, ['channel' => 'sms', 'outcome' => 'succeeded', 'attempted_at' => now()->toAtomString(), 'error_code' => 'ERR'], $headers)->assertUnprocessable();
    }

    public function test_delivery_evidence_is_throttled_per_session(): void
    {
        $f = $this->fixture(['receipts.delivery.record']);
        $url = "/api/v1/receipts/{$f['receipt']}/delivery-attempts";
        $this->withToken($f['token'])->withHeader('X-Tenant-ID', $f['tenant']);
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->postJson($url, ['channel' => 'printer', 'outcome' => 'succeeded', 'attempted_at' => now()->toAtomString()])->assertCreated();
        }
        $this->postJson($url, ['channel' => 'printer', 'outcome' => 'succeeded', 'attempted_at' => now()->toAtomString()])->assertTooManyRequests();
    }

    /** @param list<string> $permissions @return array{tenant:string,token:string,receipt:string} */
    private function fixture(array $permissions): array
    {
        $suffix = bin2hex(random_bytes(3));
        $tenant = Tenant::query()->create(['name' => "Receipt {$suffix}", 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $company = Company::query()->create(['tenant_id' => $tenant->id, 'name' => "Company {$suffix}", 'status' => 'active']);
        $branch = Branch::query()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => "B{$suffix}", 'name' => 'Branch', 'status' => 'active']);
        $warehouse = Warehouse::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'code' => "W{$suffix}", 'name' => 'Warehouse', 'status' => 'active']);
        $register = Register::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'code' => "R{$suffix}", 'name' => 'Register', 'status' => 'active']);
        $user = User::factory()->create();
        $role = Role::query()->create(['tenant_id' => $tenant->id, 'name' => "role-{$suffix}"]);
        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore(['id' => $permission, 'description' => $permission]);
            DB::table('role_permissions')->insert(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'permission_id' => $permission]);
        }
        TenantMembership::query()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active']);
        $shift = (string) Str::uuid();
        $sale = (string) Str::uuid();
        $receipt = (string) Str::uuid();
        DB::table('shifts')->insert(['id' => $shift, 'tenant_id' => $tenant->id, 'register_id' => $register->id, 'opening_user_id' => $user->id, 'status' => 'open', 'currency' => 'KES', 'opening_float_minor' => 0, 'expected_cash_minor' => 0, 'opened_at' => now(), 'idempotency_key' => "shift-{$suffix}", 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sales')->insert(['id' => $sale, 'tenant_id' => $tenant->id, 'shift_id' => $shift, 'register_id' => $register->id, 'warehouse_id' => $warehouse->id, 'actor_user_id' => $user->id, 'status' => 'finalized', 'currency_code' => 'KES', 'net_minor' => 10000, 'tax_minor' => 1600, 'gross_minor' => 11600, 'idempotency_key' => "sale-{$suffix}", 'command_fingerprint' => hash('sha256', $suffix), 'finalized_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('receipts')->insert(['id' => $receipt, 'tenant_id' => $tenant->id, 'sale_id' => $sale, 'shift_id' => $shift, 'register_id' => $register->id, 'seller_id' => $user->id, 'receipt_number' => 1, 'currency_code' => 'KES', 'net_minor' => 10000, 'tax_minor' => 1600, 'gross_minor' => 11600, 'idempotency_key' => "receipt-{$suffix}", 'command_fingerprint' => hash('sha256', 'r'.$suffix), 'sale_finalized_at' => now(), 'issued_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        foreach ([2 => 'Second snapshot', 1 => 'First snapshot'] as $number => $description) {
            DB::table('receipt_lines')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'receipt_id' => $receipt, 'line_number' => $number, 'variant_id' => (string) Str::uuid(), 'description' => $description, 'quantity' => 1, 'unit_price_minor' => 5800, 'net_minor' => 5000, 'tax_minor' => 800, 'gross_minor' => 5800, 'tax_code' => 'VAT16', 'tax_rate_basis_points' => 1600, 'tax_inclusive' => true]);
        }

        return ['tenant' => (string) $tenant->id, 'token' => $this->app->make(ApiSessionManager::class)->issue($user)->token, 'receipt' => $receipt];
    }
}
