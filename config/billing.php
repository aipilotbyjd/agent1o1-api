<?php

return [
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
