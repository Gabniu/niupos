<?php

declare(strict_types=1);

namespace App\Modules\Search\Application;

use InvalidArgumentException;

final readonly class SearchDocument
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $documentType,
        public string $documentId,
        public string $title,
        public string $searchableText,
        public array $payload,
        public int $sourceVersion,
    ) {
        if ($this->documentType === '' || mb_strlen($this->documentType) > 64 || $this->documentId === '' || mb_strlen($this->documentId) > 128) {
            throw new InvalidArgumentException('Search document identity is invalid.');
        }
        if ($this->sourceVersion < 0) {
            throw new InvalidArgumentException('Search document source version cannot be negative.');
        }
    }
}
