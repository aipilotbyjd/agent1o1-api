<?php

namespace App\Services\SuperAdmin\System\Checks;

use Illuminate\Support\Facades\Redis;

/**
 * Pings Redis, but only when something here is configured to use it.
 */
class RedisCheck implements HealthCheck
{
    public function name(): string
    {
        return 'redis';
    }

    public function run(): array
    {
        $used = in_array('redis', [
            config('cache.default'),
            config('queue.default'),
            config('session.driver'),
            config('broadcasting.default'),
        ], true) || config('cache.stores.'.config('cache.default').'.driver') === 'redis';

        if (! $used) {
            return ['status' => self::SKIPPED, 'message' => 'Nothing is configured to use Redis.'];
        }

        Redis::connection()->ping();

        return ['status' => self::HEALTHY, 'message' => 'Redis answered a ping.'];
    }
}
