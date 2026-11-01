<?php

namespace App\Services\SuperAdmin\System\Checks;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageCheck implements HealthCheck
{
    public function name(): string
    {
        return 'storage';
    }

    public function run(): array
    {
        $path = 'system-health/'.Str::random(8).'.txt';
        $disk = Storage::disk();

        $disk->put($path, 'ok');
        $ok = $disk->get($path) === 'ok';
        $disk->delete($path);

        return $ok
            ? ['status' => self::HEALTHY, 'message' => 'The default disk is writable.', 'details' => ['disk' => config('filesystems.default')]]
            : ['status' => self::DOWN, 'message' => 'The default disk did not return what was written.'];
    }
}
