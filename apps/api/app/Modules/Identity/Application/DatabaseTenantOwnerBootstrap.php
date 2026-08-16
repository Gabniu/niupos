<?php
declare(strict_types=1);
namespace App\Modules\Identity\Application;
use App\Modules\Audit\Application\Contracts\SecurityAuditRecorder;
use App\Modules\Audit\Application\SecurityAuditEvent;
use App\Modules\Identity\Application\Contracts\TenantOwnerBootstrap;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;
use App\Modules\Tenancy\Application\TenantScope;
use DomainException;
use Illuminate\Support\Facades\DB;
final readonly class DatabaseTenantOwnerBootstrap implements TenantOwnerBootstrap {
    public function __construct(private TenantScope $scope, private SecurityAuditRecorder $audit) {}
    public function bootstrap(string $tenantId, User $owner, string $operatorReference = 'automated-test'): TenantMembership {
        if (trim($operatorReference) === '') throw new DomainException('An operator reference is required.');
        return $this->scope->runFor($tenantId, function () use ($tenantId, $owner, $operatorReference): TenantMembership {
            return DB::transaction(function () use ($tenantId, $owner, $operatorReference): TenantMembership {
                $tenant=DB::table('tenants')->where('id',$tenantId)->lockForUpdate()->first();
                if ($tenant === null) throw new DomainException('Tenant does not exist.');
                if (TenantMembership::query()->where('tenant_id',$tenantId)->exists()) throw new DomainException('Tenant ownership has already been bootstrapped.');
                $keys=['iam.roles.manage'=>'Manage tenant roles','iam.memberships.manage'=>'Manage tenant memberships','channels.registrations.manage'=>'Manage customer channel registrations','onboarding.provision'=>'Preview and approve onboarding provisioning'];
                foreach($keys as $key=>$description) DB::table('permissions')->updateOrInsert(['id'=>$key],['description'=>$description,'created_at'=>now(),'updated_at'=>now()]);
                $role=Role::query()->create(['tenant_id'=>$tenantId,'name'=>'tenant-owner']);
                foreach(array_keys($keys) as $key) DB::table('role_permissions')->insert(['tenant_id'=>$tenantId,'role_id'=>$role->getKey(),'permission_id'=>$key]);
                $membership=TenantMembership::query()->create(['tenant_id'=>$tenantId,'user_id'=>$owner->getKey(),'role_id'=>$role->getKey(),'status'=>'active','is_owner'=>true]);
                $this->audit->record(new SecurityAuditEvent('identity.owner.bootstrapped',(string)$owner->getKey(),['membership_id'=>(string)$membership->getKey(),'role_id'=>(string)$role->getKey(),'operator_reference_hash'=>hash('sha256',$operatorReference)],$tenantId));
                return $membership;
            });
        });
    }
}
