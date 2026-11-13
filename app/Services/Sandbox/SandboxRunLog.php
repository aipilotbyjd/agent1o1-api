<?php

namespace App\Services\Sandbox;

use App\Enums\Sandbox\SandboxMode;
use App\Models\Sandbox\SandboxProvider;
use App\Models\Sandbox\SandboxRun;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Str;

/**
 * Records every code run and answers the questions the limits ask of that
 * record: this workspace's seconds today, the month's cost so far.
 */
class SandboxRunLog
{
    public function record(?Workspace $workspace, SandboxProvider $provider, string $source, SandboxMode $mode, string $language, string $status, int $seconds, ?string $error = null): SandboxRun
    {
        return SandboxRun::query()->create([
            'workspace_id' => $workspace?->id,
            'provider' => $provider->key,
            'source' => $source,
            'mode' => $mode->value,
            'language' => $language,
            'status' => $status,
            'seconds' => $seconds,
            'cost_usd' => $provider->costFor($seconds),
            'error' => $error !== null ? Str::limit($error, 500, '') : null,
        ]);
    }

    public function workspaceSecondsToday(Workspace $workspace): int
    {
        return (int) SandboxRun::query()
            ->where('workspace_id', $workspace->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('seconds');
    }

    public function monthCostUsd(): float
    {
        return (float) SandboxRun::query()->where('created_at', '>=', now()->startOfMonth())->sum('cost_usd');
    }
}
