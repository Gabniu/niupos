<?php

use App\Modules\Audit\AuditServiceProvider;
use App\Modules\Catalogue\CatalogueServiceProvider;
use App\Modules\Channels\ChannelsServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Inventory\InventoryServiceProvider;
use App\Modules\Onboarding\OnboardingServiceProvider;
use App\Modules\Payments\PaymentsServiceProvider;
use App\Modules\Pricing\PricingServiceProvider;
use App\Modules\Receipts\ReceiptsServiceProvider;
use App\Modules\Reports\ReportsServiceProvider;
use App\Modules\Register\RegisterServiceProvider;
use App\Modules\Search\SearchServiceProvider;
use App\Modules\Sales\SalesServiceProvider;
use App\Modules\Shifts\ShiftsServiceProvider;
use App\Modules\Sync\SyncServiceProvider;
use App\Modules\Tenancy\TenancyServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    AuditServiceProvider::class,
    IdentityServiceProvider::class,
    InventoryServiceProvider::class,
    OnboardingServiceProvider::class,
    PaymentsServiceProvider::class,
    CatalogueServiceProvider::class,
    ChannelsServiceProvider::class,
    ReceiptsServiceProvider::class,
    ReportsServiceProvider::class,
    SearchServiceProvider::class,
    RegisterServiceProvider::class,
    SalesServiceProvider::class,
    ShiftsServiceProvider::class,
    PricingServiceProvider::class,
    SyncServiceProvider::class,
];
