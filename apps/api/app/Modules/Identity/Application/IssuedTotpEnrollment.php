<?php
declare(strict_types=1);
namespace App\Modules\Identity\Application;
final readonly class IssuedTotpEnrollment { public function __construct(public string $secret, public string $otpauthUri) {} }
