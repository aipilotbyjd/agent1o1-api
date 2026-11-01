<?php

namespace App\Services\SuperAdmin\System\Checks;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Writes and reads back a value in the default cache store, which is also
 * where locks, throttles and the scheduler's heartbeat live.
 */
class CacheCheck implements HealthCheck
{
    public function name(): string
    {
        return 'cache';
    }

    public function run(): array
    {
        $key = 'system-health:probe:'.Str::random(8);
        $value = Str::random(12);

        Cache::put($key, $value, 30);
        $read = Cache::get($key);
        Cache::forget($key);

        if ($read !== $value) {
            return ['status' => self::DOWN, 'message' => 'The cache did not return what was written to it.'];
        }

        return [
            'status' => self::HEALTHY,
            'message' => 'The cache stored and returned a value.',
            'details' => ['store' => config('cache.default')],
        ];
    }
}
