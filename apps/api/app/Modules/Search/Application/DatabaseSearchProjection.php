<?php

declare(strict_types=1);

namespace App\Modules\Search\Application;

use App\Modules\Search\Application\Contracts\SearchProjection;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class DatabaseSearchProjection implements SearchProjection
{
    public function __construct(private TenantContext $context) {}

    public function upsert(SearchDocument $document): void
    {
        $now = now();
        DB::table('search_projection_documents')->updateOrInsert(
            ['tenant_id' => (string) $this->context->id(), 'document_type' => $document->documentType, 'document_id' => $document->documentId],
            [
                'title' => $document->title,
                'searchable_text' => mb_strtolower(trim($document->searchableText)),
                'payload' => json_encode($document->payload, JSON_THROW_ON_ERROR),
                'source_version' => $document->sourceVersion,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    public function delete(string $documentType, string $documentId): void
    {
        DB::table('search_projection_documents')
            ->where('tenant_id', (string) $this->context->id())
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->delete();
    }

    public function search(string $query, int $limit = 20): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new RuntimeException('Search limit must be between 1 and 100.');
        }
        $needle = mb_strtolower(trim($query));
        if ($needle === '') return [];

        return DB::table('search_projection_documents')
            ->where('tenant_id', (string) $this->context->id())
            ->where(static fn ($builder) => $builder->where('searchable_text', 'like', "%{$needle}%")->orWhere('title', 'like', "%{$needle}%"))
            ->orderBy('title')
            ->limit($limit)
            ->get(['document_type', 'document_id', 'title', 'searchable_text', 'payload', 'source_version'])
            ->map(static fn (object $row): SearchDocument => new SearchDocument(
                (string) $row->document_type,
                (string) $row->document_id,
                (string) $row->title,
                (string) $row->searchable_text,
                json_decode((string) $row->payload, true, flags: JSON_THROW_ON_ERROR),
                (int) $row->source_version,
            ))
            ->values()->all();
    }

    public function rebuild(iterable $documents): int
    {
        return DB::transaction(function () use ($documents): int {
            $tenantId = (string) $this->context->id();
            DB::table('search_projection_documents')->where('tenant_id', $tenantId)->delete();
            $count = 0;
            foreach ($documents as $document) {
                if (! $document instanceof SearchDocument) {
                    throw new RuntimeException('Search rebuild received an invalid document.');
                }
                $this->upsert($document);
                $count += 1;
            }

            return $count;
        });
    }
}
