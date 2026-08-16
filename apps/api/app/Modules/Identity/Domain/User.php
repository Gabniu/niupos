<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'identity_issuer', 'identity_subject'])]
#[Hidden(['password', 'remember_token', 'mfa_pending_secret', 'mfa_secret'])]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mfa_pending_secret' => 'encrypted',
            'mfa_secret' => 'encrypted',
            'mfa_confirmed_at' => 'immutable_datetime',
            'mfa_last_accepted_step' => 'integer',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
