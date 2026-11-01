<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // The Super Admin frontend's origins are added only while the panel is
    // switched on (`config/super_admin_security.php` documents the variable).
    'allowed_origins' => array_values(array_unique(array_filter(array_map(
        'trim',
        explode(',', implode(',', [
            (string) env('CORS_ALLOWED_ORIGINS', env('APP_FRONTEND_URL', 'http://localhost:5173')),
            env('SUPER_ADMIN_ENABLED', false) ? (string) env('SUPER_ADMIN_ALLOWED_ORIGINS', '') : '',
        ])),
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
