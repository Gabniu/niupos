<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Console;

use App\Modules\Identity\Domain\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

final class CreateUserCommand extends Command
{
    protected $signature = 'nova:user:create
        {--name= : Full name; prompted when omitted}
        {--email= : Email address; prompted when omitted}';

    protected $description = 'Create a local POS user using hidden password prompts';

    public function handle(): int
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('Full name')));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Email address'))));

        if ($name === '' || mb_strlen($name) > 255) {
            $this->error('A valid full name is required.');
            return self::INVALID;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 254) {
            $this->error('A valid email address is required.');
            return self::INVALID;
        }
        if (User::query()->where('email', $email)->exists()) {
            $this->error('A user with that email already exists.');
            return self::FAILURE;
        }

        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Confirm password');
        if ($password === '' || mb_strlen($password) < 12) {
            $this->error('Password must be at least 12 characters.');
            return self::INVALID;
        }
        if (! hash_equals($password, $confirmation)) {
            $this->error('The passwords do not match.');
            return self::INVALID;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info('User created.');
        $this->line('User UUID: '.$user->getKey());
        return self::SUCCESS;
    }
}
