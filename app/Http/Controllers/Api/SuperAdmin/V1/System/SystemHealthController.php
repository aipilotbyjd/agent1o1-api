<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\System;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\SuperAdmin\System\HealthChecker;
use App\Services\SuperAdmin\System\HealthSnapshotter;
use App\Services\SuperAdmin\System\HorizonInspector;
use App\Services\SuperAdmin\System\QueueInspector;
use App\Services\SuperAdmin\System\SchedulerMonitor;
use App\Services\SuperAdmin\System\StuckWorkDetector;
use Illuminate\Http\Request;

class SystemHealthController extends Controller
{
    /**
     * The connectivity checks of `GET /system/services`.
     *
     * @var list<string>
     */
    private const SERVICES = ['database', 'cache', 'redis', 'reverb', 'mail', 'stripe', 'storage', 'disk'];

    public function __construct(
        private readonly HealthChecker $checker,
        private readonly HealthSnapshotter $snapshots,
        private readonly QueueInspector $queues,
        private readonly SchedulerMonitor $scheduler,
        private readonly HorizonInspector $horizon,
        private readonly StuckWorkDetector $stuck,
    ) {}

    public function health()
    {
        return ApiResponse::success($this->checker->run());
    }

    public function services()
    {
        $services = [];

        foreach (self::SERVICES as $name) {
            $services[$name] = $this->checker->runOne($name);
        }

        return ApiResponse::success($services);
    }

    public function queues()
    {
        return ApiResponse::success(['driver' => $this->queues->driver(), 'queues' => $this->queues->queues()]);
    }

    public function scheduler()
    {
        $beat = $this->scheduler->heartbeatAt();

        return ApiResponse::success([
            'heartbeat_at' => $beat?->toIso8601String(),
            'heartbeat_age_seconds' => $beat === null ? null : (int) $beat->diffInSeconds(now(), true),
            'commands' => $this->scheduler->commands(),
        ]);
    }

    public function horizon()
    {
        return ApiResponse::success($this->horizon->summary());
    }

    public function stuck()
    {
        return ApiResponse::success($this->stuck->report());
    }

    public function history(Request $request)
    {
        $request->validate(['hours' => ['nullable', 'integer', 'min:1', 'max:720']]);

        $hours = $request->integer('hours', 24);

        return ApiResponse::success([
            'uptime' => $this->snapshots->uptime(),
            'incidents' => $this->snapshots->incidents(max(1, (int) ceil($hours / 24))),
            'timeline' => $this->snapshots->timeline($hours),
        ]);
    }
}
