<?php

/**
 * Evaluates config/reverb.php under a given environment, since `config()`
 * is already resolved by the time a test runs.
 *
 * @param  array<string, string>  $env
 * @return array<string, mixed>
 */
function reverbApp(array $env = []): array
{
    $keys = ['REVERB_ALLOWED_ORIGINS', 'APP_FRONTEND_URL', 'REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM', 'REVERB_APP_RATE_LIMITING_ENABLED'];
    $previous = array_map(fn (string $key) => [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)], array_combine($keys, $keys));

    foreach ($keys as $key) {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }

    foreach ($env as $key => $value) {
        $_ENV[$key] = $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }

    try {
        return (require config_path('reverb.php'))['apps']['apps'][0];
    } finally {
        foreach ($keys as $key) {
            [$envValue, $serverValue, $processValue] = $previous[$key];
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);

            if ($envValue !== null) {
                $_ENV[$key] = $envValue;
            }

            if ($serverValue !== null) {
                $_SERVER[$key] = $serverValue;
            }

            if ($processValue !== false) {
                putenv("{$key}={$processValue}");
            }
        }
    }
}

it('only accepts websocket connections from the frontend origin by default', function () {
    $app = reverbApp(['APP_FRONTEND_URL' => 'https://app.example.com']);

    expect($app['allowed_origins'])->toBe(['app.example.com'])
        ->and($app['allowed_origins'])->not->toContain('*');
});

it('lets the allowed origins be listed explicitly', function () {
    $app = reverbApp(['REVERB_ALLOWED_ORIGINS' => 'app.example.com, *.staging.example.com']);

    expect($app['allowed_origins'])->toBe(['app.example.com', '*.staging.example.com']);
});

it('never allows an origin when none is configured', function () {
    expect(reverbApp(['APP_FRONTEND_URL' => '', 'REVERB_ALLOWED_ORIGINS' => ''])['allowed_origins'])->toBe([]);
});

it('refuses client events and rate limits connections by default', function () {
    $app = reverbApp();

    expect($app['accept_client_events_from'])->toBe('none')
        ->and($app['rate_limiting']['enabled'])->toBeTrue();
});
