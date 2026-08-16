<?php
declare(strict_types=1);
namespace App\Modules\Identity\Infrastructure;
use App\Modules\Audit\Application\Contracts\SecurityAuditRecorder;
use App\Modules\Audit\Application\SecurityAuditEvent;
use App\Modules\Identity\Application\Contracts\TotpManager;
use App\Modules\Identity\Application\IssuedTotpEnrollment;
use App\Modules\Identity\Domain\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
final readonly class DatabaseTotpManager implements TotpManager {
 private const ALPHABET='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
 public function __construct(private SecurityAuditRecorder $audit) {}
 public function begin(User $user): IssuedTotpEnrollment {
  $secret=$this->base32Encode(random_bytes(20));
  $user->forceFill(['mfa_pending_secret'=>$secret])->save();
  $label=rawurlencode('NOVA POS:'.$user->email);
  return new IssuedTotpEnrollment($secret,"otpauth://totp/{$label}?secret={$secret}&issuer=NOVA%20POS&algorithm=SHA1&digits=6&period=30");
 }
 public function confirm(User $user,string $code): bool {
  $secret=$user->mfa_pending_secret;
  if(!is_string($secret)||!$this->verifySecret($secret,$code)) return false;
  DB::transaction(function()use($user,$secret):void{$user->forceFill(['mfa_secret'=>$secret,'mfa_pending_secret'=>null,'mfa_confirmed_at'=>Date::now()])->save();$this->audit->record(new SecurityAuditEvent('identity.mfa.totp_enabled',(string)$user->getKey(),['factor'=>'totp']));});
  return true;
 }
 public function verify(User $user,string $code): bool {
  $secret=$user->mfa_secret;
  return $user->mfa_confirmed_at!==null&&is_string($secret)&&$this->verifySecret($secret,$code);
 }
 public function verifyAndConsume(User $user,string $code): bool {
  return DB::transaction(function()use($user,$code):bool{
   $locked=User::query()->lockForUpdate()->find($user->getKey());
   if(!$locked instanceof User||$locked->mfa_confirmed_at===null||!is_string($locked->mfa_secret))return false;
   $step=$this->matchingStep($locked->mfa_secret,$code);
   if($step===null||($locked->mfa_last_accepted_step!==null&&$step<=(int)$locked->mfa_last_accepted_step))return false;
   $locked->forceFill(['mfa_last_accepted_step'=>$step])->save();
   return true;
  });
 }
 private function verifySecret(string $secret,string $code): bool {
  return $this->matchingStep($secret,$code)!==null;
 }
 private function matchingStep(string $secret,string $code):?int {
  if(preg_match('/^\d{6}$/',$code)!==1)return null; $step=intdiv(Date::now()->getTimestamp(),30);
  for($offset=-1;$offset<=1;$offset++){ $candidate=$step+$offset;if(hash_equals($this->code($secret,$candidate),$code))return $candidate; }
  return null;
 }
 private function code(string $secret,int $counter):string {
  $binary=pack('N2',intdiv($counter,4294967296),$counter%4294967296);$hash=hash_hmac('sha1',$binary,$this->base32Decode($secret),true);$offset=ord($hash[19])&15;$value=((ord($hash[$offset])&127)<<24)|((ord($hash[$offset+1])&255)<<16)|((ord($hash[$offset+2])&255)<<8)|(ord($hash[$offset+3])&255);return str_pad((string)($value%1000000),6,'0',STR_PAD_LEFT);
 }
 private function base32Encode(string $data):string {$bits='';foreach(str_split($data)as$c)$bits.=str_pad(decbin(ord($c)),8,'0',STR_PAD_LEFT);$out='';foreach(str_split($bits,5)as$chunk)$out.=self::ALPHABET[bindec(str_pad($chunk,5,'0'))];return $out;}
 private function base32Decode(string $data):string {$bits='';foreach(str_split($data)as$c){$p=strpos(self::ALPHABET,$c);if($p===false)return '';$bits.=str_pad(decbin($p),5,'0',STR_PAD_LEFT);} $out='';foreach(str_split($bits,8)as$chunk)if(strlen($chunk)===8)$out.=chr(bindec($chunk));return $out;}
}
