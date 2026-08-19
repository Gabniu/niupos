<?php

declare(strict_types=1);

return [
    'driver' => env('SEARCH_DRIVER', 'database'),
    'elasticsearch' => [
        'url' => rtrim((string) env('ELASTICSEARCH_URL', 'http://127.0.0.1:9200'), '/'),
        'index_prefix' => (string) env('ELASTICSEARCH_INDEX_PREFIX', 'niu-search'),
        'timeout_seconds' => (int) env('ELASTICSEARCH_TIMEOUT_SECONDS', 5),
    ],
];
