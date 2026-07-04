<?php

return [
    // Credits consumed per workflow execution. Metered when an execution starts.
    'credits_per_execution' => (int) env('CREDITS_PER_EXECUTION', 1),

    // Per-user request ceiling (requests/minute) for the authenticated API.
    'api_rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 300),

    'packs' => [
        'small' => [
            'label' => '5,000 credits',
            'credits' => 5000,
            'price_cents' => 900,
            'stripe_price_id' => env('STRIPE_PRICE_PACK_SMALL'),
        ],
        'medium' => [
            'label' => '15,000 credits',
            'credits' => 15000,
            'price_cents' => 2400,
            'stripe_price_id' => env('STRIPE_PRICE_PACK_MEDIUM'),
        ],
        'large' => [
            'label' => '50,000 credits',
            'credits' => 50000,
            'price_cents' => 6900,
            'stripe_price_id' => env('STRIPE_PRICE_PACK_LARGE'),
        ],
    ],
];
