<?php
declare(strict_types=1);
namespace App\Modules\Identity\Application\Contracts;
use App\Modules\Identity\Application\IssuedTotpEnrollment;
use App\Modules\Identity\Domain\User;
interface TotpManager {
 public function begin(User $user): IssuedTotpEnrollment;
 public function confirm(User $user,string $code): bool;
 public function verify(User $user,string $code): bool;
 public function verifyAndConsume(User $user,string $code): bool;
}
