<?php

namespace App\Console\Commands;

use App\Enums\ExecutionStatus;
use App\Models\Agent;
use App\Models\Run;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Console\Command;

class AdminPlatformHealthCommand extends Command
{
    protected $signature = 'platform:health';

    protected $description = 'Report high-level platform health metrics.';

    public function handle(): int
    {
        $metrics = [
            'users' => User::count(),
            'workspaces' => Workspace::count(),
            'workflows' => Workflow::count(),
            'active_workflows' => Workflow::where('is_active', true)->count(),
            'agents' => Agent::count(),
            'executions_24h' => Run::where('created_at', '>=', now()->subDay())->count(),
            'failed_24h' => Run::where('created_at', '>=', now()->subDay())
                ->where('status', ExecutionStatus::Failed)->count(),
        ];

        foreach ($metrics as $label => $value) {
            $this->line(str_pad($label, 20).': '.$value);
        }

        return self::SUCCESS;
    }
}
