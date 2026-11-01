<?php

namespace App\Services\SuperAdmin\System\Checks;

use App\Services\Http\LocalDevelopmentRequest;

/**
 * Whether the app is configured for the place it is served from. A server
 * reached by a public address but left on `APP_ENV=local` relaxes the
 * safeguards meant for a developer's machine, and `APP_DEBUG` there would
 * show stack traces to visitors.
 */
class EnvironmentCheck implements HealthCheck
{
    public function __construct(private readonly LocalDevelopmentRequest $local) {}

    public function name(): string
    {
        return 'environment';
    }

    public function run(): array
    {
        $environment = (string) app()->environment();
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $isDevelopmentHost = $this->local->isDevelopmentHost($host);
        $details = ['environment' => $environment, 'host' => $host, 'debug' => (bool) config('app.debug')];

        return match (true) {
            $environment === 'local' && ! $isDevelopmentHost => [
                'status' => self::DOWN,
                'message' => "APP_ENV is 'local' but APP_URL points at {$host}. Set APP_ENV=production: local relaxes the Stripe webhook signature and the Horizon and Pulse gates.",
                'details' => $details,
            ],
            config('app.debug') && ! $isDevelopmentHost => [
                'status' => self::DEGRADED,
                'message' => "APP_DEBUG is on while APP_URL points at {$host}. Turn it off so errors don't show stack traces.",
                'details' => $details,
            ],
            default => [
                'status' => self::HEALTHY,
                'message' => "Running as '{$environment}' for {$host}.",
                'details' => $details,
            ],
        };
    }
}
