<?php

declare(strict_types=1);

namespace App\Modules\Search\Application;

use App\Modules\Search\Application\Contracts\SearchProjection;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class ElasticsearchSearchProjection implements SearchProjection
{
    public function __construct(private TenantContext $tenantContext) {}

    public function upsert(SearchDocument $document): void
    {
        $this->client()->put($this->documentPath($this->alias(), $document->documentId), $this->source($document))->throw();
    }

    public function delete(string $documentType, string $documentId): void
    {
        $response = $this->client()->delete($this->documentPath($this->alias(), $documentId));
        if ($response->status() !== 404) {
            $response->throw();
        }
    }

    public function search(string $query, int $limit = 20): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new RuntimeException('Search limit must be between 1 and 100.');
        }
        $needle = mb_strtolower(trim($query));
        if ($needle === '') {
            return [];
        }
        $hits = $this->client()->post('/'.rawurlencode($this->alias()).'/_search', [
            'size' => $limit,
            'query' => ['multi_match' => ['query' => $needle, 'fields' => ['title^2', 'searchable_text']]],
        ])->throw()->json('hits.hits', []);

        return array_values(array_map(static fn (array $hit): SearchDocument => self::documentFromHit($hit), $hits));
    }

    public function rebuild(iterable $documents): int
    {
        $index = $this->alias().'-'.Str::lower((string) Str::uuid());
        $count = 0;
        try {
            $this->client()->put('/'.rawurlencode($index))->throw();
            $bulk = '';
            foreach ($documents as $document) {
                if (! $document instanceof SearchDocument) {
                    throw new RuntimeException('Search rebuild received an invalid document.');
                }
                $bulk .= json_encode(['index' => ['_index' => $index, '_id' => $document->documentId]], JSON_THROW_ON_ERROR)."\n";
                $bulk .= json_encode($this->source($document), JSON_THROW_ON_ERROR)."\n";
                $count += 1;
                if ($count % 500 === 0) {
                    $this->sendBulk($bulk);
                    $bulk = '';
                }
            }
            if ($bulk !== '') {
                $this->sendBulk($bulk);
            }
            $this->swapAlias($index);
        } catch (Throwable $exception) {
            $this->client()->delete('/'.rawurlencode($index));
            throw $exception;
        }

        return $count;
    }

    private function sendBulk(string $body): void
    {
        $response = $this->client()->withBody($body, 'application/x-ndjson')->post('/_bulk');
        $response->throw();
        if ($response->json('errors', false) === true) {
            throw new RuntimeException('Elasticsearch bulk indexing returned item errors.');
        }
    }

    private function swapAlias(string $index): void
    {
        $existing = $this->client()->get('/'.rawurlencode($this->alias()));
        $oldIndexes = $existing->status() === 404 ? [] : array_keys($existing->throw()->json());
        $actions = array_map(static fn (string $old): array => ['remove' => ['index' => $old, 'alias' => $this->alias()]], $oldIndexes);
        $actions[] = ['add' => ['index' => $index, 'alias' => $this->alias()]];
        $this->client()->post('/_aliases', ['actions' => $actions])->throw();
    }

    /** @return array<string, mixed> */
    private function source(SearchDocument $document): array
    {
        return [
            'document_type' => $document->documentType,
            'document_id' => $document->documentId,
            'title' => $document->title,
            'searchable_text' => mb_strtolower(trim($document->searchableText)),
            'payload' => $document->payload,
            'source_version' => $document->sourceVersion,
        ];
    }

    /** @param array<string, mixed> $hit */
    private static function documentFromHit(array $hit): SearchDocument
    {
        $source = is_array($hit['_source'] ?? null) ? $hit['_source'] : [];

        return new SearchDocument(
            (string) ($source['document_type'] ?? ''),
            (string) ($source['document_id'] ?? ($hit['_id'] ?? '')),
            (string) ($source['title'] ?? ''),
            (string) ($source['searchable_text'] ?? ''),
            is_array($source['payload'] ?? null) ? $source['payload'] : [],
            (int) ($source['source_version'] ?? 0),
        );
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('search.elasticsearch.url'))->timeout((int) config('search.elasticsearch.timeout_seconds', 5))->acceptJson();
    }

    private function alias(): string
    {
        $prefix = strtolower((string) config('search.elasticsearch.index_prefix', 'niu-search'));
        $tenant = strtolower((string) $this->tenantContext->id());
        $alias = preg_replace('/[^a-z0-9_-]+/', '-', $prefix.'-'.$tenant);

        return trim((string) $alias, '-_');
    }

    private function documentPath(string $index, string $documentId): string
    {
        return '/'.rawurlencode($index).'/_doc/'.rawurlencode($documentId);
    }
}
