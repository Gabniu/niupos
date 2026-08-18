<?php

declare(strict_types=1);

namespace App\Modules\Search\Application\Contracts;

use App\Modules\Search\Application\SearchDocument;

interface SearchProjection
{
    public function upsert(SearchDocument $document): void;

    public function delete(string $documentType, string $documentId): void;

    /** @return list<SearchDocument> */
    public function search(string $query, int $limit = 20): array;

    /** @param iterable<SearchDocument> $documents */
    public function rebuild(iterable $documents): int;
}
