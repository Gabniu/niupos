<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalogue;

use App\Modules\Catalogue\Application\Contracts\CatalogueManager;
use App\Modules\Catalogue\Domain\UnitOfMeasure;
use App\Modules\Identity\Application\Contracts\ApiSessionManager;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ScannerHttpTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function scanner_requires_authentication_tenant_admission_and_catalogue_permission_in_that_order(): void
    {
        [$tenantId, , $token] = $this->identityFixture(withPermission: false);

        $this->withHeader('X-Tenant-ID', $tenantId)->postJson('/api/v1/catalogue/scan', [
            'value' => '12345', 'mode' => 'barcode',
        ])->assertUnauthorized();

        $this->flushHeaders();
        $this->withToken($token)->postJson('/api/v1/catalogue/scan', [
            'value' => '12345', 'mode' => 'barcode',
        ])->assertBadRequest();

        $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
            ->postJson('/api/v1/catalogue/scan', ['value' => '', 'mode' => 'invalid'])
            ->assertForbidden();
    }

    #[Test]
    public function normalized_supported_inputs_return_found_or_generic_unknown_outcomes(): void
    {
        [$tenantId, , $token] = $this->identityFixture();
        $variantId = $this->catalogueFixture($tenantId, '12345');

        foreach (['barcode', 'manual', 'keyboard_wedge', 'camera'] as $mode) {
            $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
                ->postJson('/api/v1/catalogue/scan', ['value' => ' 12 345 ', 'mode' => $mode])
                ->assertOk()
                ->assertJsonPath('data.outcome', 'found')
                ->assertJsonPath('data.normalized_value', '12345')
                ->assertJsonPath('data.variant_id', $variantId)
                ->assertJsonPath('data.weighted_ean', null);
        }

        $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
            ->postJson('/api/v1/catalogue/scan', ['value' => 'does-not-exist', 'mode' => 'manual'])
            ->assertOk()
            ->assertExactJson(['data' => [
                'outcome' => 'unknown', 'normalized_value' => 'does-not-exist',
                'variant_id' => null, 'weighted_ean' => null,
            ]]);
    }

    #[Test]
    public function scanner_does_not_reveal_another_tenants_variant(): void
    {
        [$firstTenant] = $this->identityFixture();
        $this->catalogueFixture($firstTenant, 'TENANT-ONLY');
        [$secondTenant, , $secondToken] = $this->identityFixture();

        $this->withToken($secondToken)->withHeader('X-Tenant-ID', $secondTenant)
            ->postJson('/api/v1/catalogue/scan', ['value' => 'TENANT-ONLY', 'mode' => 'camera'])
            ->assertOk()
            ->assertJsonPath('data.outcome', 'unknown')
            ->assertJsonPath('data.variant_id', null);
    }

    #[Test]
    public function weighted_ean_exposes_parsed_weight_metadata_and_resolves_only_its_item_reference(): void
    {
        [$tenantId, , $token] = $this->identityFixture();
        $variantId = $this->catalogueFixture($tenantId, '12345');

        $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
            ->postJson('/api/v1/catalogue/scan', ['value' => '2012345002507', 'mode' => 'barcode'])
            ->assertOk()
            ->assertJsonPath('data.outcome', 'found')
            ->assertJsonPath('data.variant_id', $variantId)
            ->assertJsonPath('data.weighted_ean.prefix', '20')
            ->assertJsonPath('data.weighted_ean.item_reference', '12345')
            ->assertJsonPath('data.weighted_ean.weight_grams', 250);

        $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
            ->postJson('/api/v1/catalogue/scan', ['value' => '2911111009998', 'mode' => 'barcode'])
            ->assertOk()
            ->assertJsonPath('data.outcome', 'unknown')
            ->assertJsonPath('data.weighted_ean.prefix', '29')
            ->assertJsonPath('data.weighted_ean.weight_grams', 999);
    }

    #[Test]
    public function scanner_rejects_invalid_payloads_and_is_rate_limited_per_session(): void
    {
        [$tenantId, , $token] = $this->identityFixture();
        $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
            ->postJson('/api/v1/catalogue/scan', ['value' => ' ', 'mode' => 'laser'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value', 'mode']);

        for ($attempt = 0; $attempt < 119; $attempt++) {
            $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
                ->postJson('/api/v1/catalogue/scan', ['value' => 'unknown', 'mode' => 'barcode'])
                ->assertOk();
        }
        $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
            ->postJson('/api/v1/catalogue/scan', ['value' => 'unknown', 'mode' => 'barcode'])
            ->assertTooManyRequests();
    }

    /** @return array{string, User, string} */
    private function identityFixture(bool $withPermission = true): array
    {
        $tenant = Tenant::query()->create(['name' => 'Scanner Tenant '.bin2hex(random_bytes(3)), 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $actor = User::factory()->create();
        $role = Role::query()->create(['tenant_id' => $tenant->getKey(), 'name' => 'scanner-'.bin2hex(random_bytes(3))]);
        DB::table('permissions')->insertOrIgnore(['id' => 'catalogue.products.read', 'description' => 'Read products']);
        if ($withPermission) {
            DB::table('role_permissions')->insert(['tenant_id' => $tenant->getKey(), 'role_id' => $role->getKey(), 'permission_id' => 'catalogue.products.read']);
        }
        TenantMembership::query()->create([
            'tenant_id' => $tenant->getKey(), 'user_id' => $actor->getKey(),
            'role_id' => $role->getKey(), 'status' => 'active',
        ]);

        return [(string) $tenant->getKey(), $actor, $this->app->make(ApiSessionManager::class)->issue($actor)->token];
    }

    private function catalogueFixture(string $tenantId, string $barcode): string
    {
        return $this->app->make(TenantScope::class)->runFor($tenantId, function () use ($tenantId, $barcode): string {
            $unit = UnitOfMeasure::query()->create([
                'tenant_id' => $tenantId, 'code' => 'EA'.bin2hex(random_bytes(2)),
                'name' => 'Each', 'status' => 'active',
            ]);
            $created = $this->app->make(CatalogueManager::class)->createProductWithDefaultVariant(
                'Scanner product', 'SKU-'.bin2hex(random_bytes(3)), (string) $unit->getKey(), barcode: $barcode,
            );

            return (string) $created->defaultVariant->getKey();
        });
    }
}
