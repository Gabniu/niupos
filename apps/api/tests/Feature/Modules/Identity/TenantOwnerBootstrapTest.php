<?php
declare(strict_types=1);
namespace Tests\Feature\Modules\Identity;
use App\Modules\Audit\Domain\TenantAuditEvent;
use App\Modules\Identity\Application\Contracts\TenantIamAdministration;
use App\Modules\Identity\Application\Contracts\TenantOwnerBootstrap;
use App\Modules\Identity\Domain\MembershipStatus;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
final class TenantOwnerBootstrapTest extends TestCase {
 use RefreshDatabase;
 #[Test] public function it_bootstraps_exactly_one_initial_owner_with_management_permissions_and_evidence(): void {
  $tenant=Tenant::query()->create(['name'=>'Bootstrap Tenant','jurisdiction_code'=>'KE','status'=>'active']); $owner=User::factory()->create(); $id=TenantId::fromString((string)$tenant->getKey());
  $membership=$this->app->make(TenantOwnerBootstrap::class)->bootstrap((string)$id,$owner);
  self::assertTrue($membership->is_owner); self::assertSame('active',$membership->status);
  $this->app->make(TenantScope::class)->run($id, function() use($owner): void { $role=$this->app->make(TenantIamAdministration::class)->createRole($owner,'manager'); self::assertSame('manager',$role->name); self::assertSame('identity.owner.bootstrapped',TenantAuditEvent::query()->orderBy('occurred_at')->firstOrFail()->event_type); });
  $this->expectException(DomainException::class); $this->app->make(TenantOwnerBootstrap::class)->bootstrap((string)$id,User::factory()->create());
 }
 #[Test] public function it_prevents_reassigning_or_revoking_the_last_active_owner(): void {
  $tenant=Tenant::query()->create(['name'=>'Protected Tenant','jurisdiction_code'=>'KE','status'=>'active']); $owner=User::factory()->create(); $id=TenantId::fromString((string)$tenant->getKey()); $membership=$this->app->make(TenantOwnerBootstrap::class)->bootstrap((string)$id,$owner);
  $this->expectException(DomainException::class);
  $this->app->make(TenantScope::class)->run($id, fn()=> $this->app->make(TenantIamAdministration::class)->assignMembership($owner,(string)$owner->getKey(),(string)$membership->role_id,MembershipStatus::Revoked));
 }
 #[Test] public function the_operator_cli_requires_attribution_and_rejects_replay(): void {
  $tenant=Tenant::query()->create(['name'=>'CLI Tenant','jurisdiction_code'=>'KE','status'=>'active']); $owner=User::factory()->create();
  self::assertSame(2,Artisan::call('nova:tenant:bootstrap-owner',['tenant'=>$tenant->getKey(),'user'=>$owner->getKey(),'--force'=>true]));
  self::assertSame(0,Artisan::call('nova:tenant:bootstrap-owner',['tenant'=>$tenant->getKey(),'user'=>$owner->getKey(),'--operator'=>'change-123','--force'=>true]));
  self::assertSame(hash('sha256','change-123'),TenantAuditEvent::query()->firstOrFail()->metadata['operator_reference_hash']);
  self::assertSame(1,Artisan::call('nova:tenant:bootstrap-owner',['tenant'=>$tenant->getKey(),'user'=>$owner->getKey(),'--operator'=>'change-124','--force'=>true]));
 }
 #[Test] public function an_active_owner_can_atomically_transfer_ownership_to_an_active_member(): void {
  $tenant=Tenant::query()->create(['name'=>'Transfer Tenant','jurisdiction_code'=>'KE','status'=>'active']); $owner=User::factory()->create(); $target=User::factory()->create(); $id=(string)$tenant->getKey();
  $ownerMembership=$this->app->make(TenantOwnerBootstrap::class)->bootstrap($id,$owner);
  $this->app->make(TenantScope::class)->runFor($id,function() use($owner,$target,$ownerMembership): void {
   $admin=$this->app->make(TenantIamAdministration::class);
   $admin->assignMembership($owner,(string)$target->getKey(),(string)$ownerMembership->role_id,MembershipStatus::Active);
   $admin->transferOwnership($owner,(string)$target->getKey());
  });
  self::assertFalse($ownerMembership->fresh()->is_owner);
  self::assertTrue(TenantMembership::query()->where('tenant_id',$id)->where('user_id',$target->getKey())->firstOrFail()->is_owner);
  self::assertSame('identity.owner.transferred',TenantAuditEvent::query()->where('event_type','identity.owner.transferred')->firstOrFail()->event_type);
 }
}
