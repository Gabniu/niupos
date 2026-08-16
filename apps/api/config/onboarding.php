<?php

return [
    'delivery' => [
        'max_attempts' => (int) env('ONBOARDING_DELIVERY_MAX_ATTEMPTS', 3),
    ],
];
