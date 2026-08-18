<?php

declare(strict_types=1);

return [
    /*
    | A sale may be created while a device is offline for weeks, so the age
    | window is intentionally generous. The future window stays short because
    | a device clock ahead of the server must not create misleading chronology.
    */
    'command_clock' => [
        'max_age_seconds' => (int) env('SYNC_COMMAND_MAX_AGE_SECONDS', 2_592_000), // 30 days
        'max_future_seconds' => (int) env('SYNC_COMMAND_MAX_FUTURE_SECONDS', 900), // 15 minutes
    ],
];
