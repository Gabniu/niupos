<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Data;

final readonly class SyncCommandOutcome
{
    /** @param array<string, mixed>|null $evidence */
    public function __construct(
        public string $status,
        public ?string $code = null,
        public ?string $message = null,
        public ?array $evidence = null,
    ) {}

    public static function applied(): self
    {
        return new self('applied');
    }

    public static function rejected(string $code, string $message): self
    {
        return new self('rejected', $code, $message);
    }

    /** @param array<string, mixed> $evidence */
    public static function conflict(string $code, string $message, array $evidence): self
    {
        return new self('conflict', $code, $message, $evidence);
    }

    public static function retry(string $code, string $message): self
    {
        return new self('retry_pending', $code, $message);
    }
}
