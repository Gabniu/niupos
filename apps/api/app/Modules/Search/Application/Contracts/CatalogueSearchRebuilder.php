<?php

declare(strict_types=1);

namespace App\Modules\Search\Application\Contracts;

interface CatalogueSearchRebuilder
{
    public function rebuild(): int;
}
