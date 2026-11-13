<?php

namespace App\Services\Sandbox;

use App\Models\Sandbox\SandboxProvider;

/**
 * Builds the driver for a provider row.
 */
class SandboxManager
{
    public function driver(SandboxProvider $provider): SandboxDriver
    {
        return app()->make($provider->type()->driverClass(), ['provider' => $provider]);
    }
}
