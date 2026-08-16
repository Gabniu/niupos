<?php
declare(strict_types=1);
namespace Tests\Feature\Modules\Identity;
use App\Modules\Audit\Domain\AuditEvent;
use App\Modules\Identity\Application\Contracts\TotpManager;
use App\Modules\Identity\Domain\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
final class TotpManagerTest extends TestCase {
 use RefreshDatabase;
 #[Test] public function enrollment_is_encrypted_confirmed_by_totp_and_audited_without_the_secret():void {
  Date::setTestNow('2026-08-08 12:00:00');$user=User::factory()->create();$manager=$this->app->make(TotpManager::class);$issued=$manager->begin($user);$raw=DB::table('users')->where('id',$user->getKey())->value('mfa_pending_secret');
  self::assertSame(32,strlen($issued->secret));self::assertNotSame($issued->secret,$raw);self::assertStringContainsString('otpauth://totp/', $issued->otpauthUri);self::assertFalse($manager->confirm($user->fresh(),'000000'));
  $code=$this->code($issued->secret,Date::now()->getTimestamp());self::assertTrue($manager->confirm($user->fresh(),$code));self::assertTrue($manager->verify($user->fresh(),$code));
  Date::setTestNow('2026-08-08 12:00:30');self::assertTrue($manager->verify($user->fresh(),$code));$event=AuditEvent::query()->where('event_type','identity.mfa.totp_enabled')->firstOrFail();self::assertStringNotContainsString($issued->secret,json_encode($event->metadata,JSON_THROW_ON_ERROR));Date::setTestNow();
 }
 private function code(string $secret,int $timestamp):string {$alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';foreach(str_split($secret)as$c)$bits.=str_pad(decbin((int)strpos($alphabet,$c)),5,'0',STR_PAD_LEFT);$key='';foreach(str_split($bits,8)as$chunk)if(strlen($chunk)===8)$key.=chr(bindec($chunk));$counter=intdiv($timestamp,30);$binary=pack('N2',intdiv($counter,4294967296),$counter%4294967296);$hash=hash_hmac('sha1',$binary,$key,true);$offset=ord($hash[19])&15;$value=((ord($hash[$offset])&127)<<24)|((ord($hash[$offset+1])&255)<<16)|((ord($hash[$offset+2])&255)<<8)|(ord($hash[$offset+3])&255);return str_pad((string)($value%1000000),6,'0',STR_PAD_LEFT);}
}
