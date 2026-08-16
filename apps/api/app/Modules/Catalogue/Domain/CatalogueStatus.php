<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Domain;

enum CatalogueStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
