<?php

namespace App\Services\SuperAdmin\System;

use Illuminate\Support\Facades\Artisan;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Throwable;

/**
 * Horizon's state, read through its own repositories. Only meaningful on a
 * Redis queue: on any other driver every method reports "not applicable".
 */
class HorizonInspector
{
    public function applicable(): bool
    {
        return config('queue.default') === 'redis' && interface_exists(MasterSupervisorRepository::class);
    }

    /**
     * @return array{applicable: bool, status: string, masters: int, supervisors: int, processes: int}
     */
    public function summary(): array
    {
        if (! $this->applicable()) {
            return ['applicable' => false, 'status' => 'inactive', 'masters' => 0, 'supervisors' => 0, 'processes' => 0];
        }

        try {
            $masters = collect(app(MasterSupervisorRepository::class)->all());
            $supervisors = collect(app(SupervisorRepository::class)->all());
        } catch (Throwable $e) {
            report($e);

            return ['applicable' => true, 'status' => 'inactive', 'masters' => 0, 'supervisors' => 0, 'processes' => 0];
        }

        $status = match (true) {
            $masters->isEmpty() => 'inactive',
            $masters->contains(fn ($master): bool => ($master->status ?? null) === 'paused') => 'paused',
            default => 'running',
        };

        return [
            'applicable' => true,
            'status' => $status,
            'masters' => $masters->count(),
            'supervisors' => $supervisors->count(),
            'processes' => $supervisors->sum(fn ($supervisor): int => array_sum((array) ($supervisor->processes ?? []))),
        ];
    }

    public function pause(): void
    {
        abort_unless($this->applicable(), 409, 'Horizon only runs on a Redis queue.');

        Artisan::call('horizon:pause');
    }

    public function resume(): void
    {
        abort_unless($this->applicable(), 409, 'Horizon only runs on a Redis queue.');

        Artisan::call('horizon:continue');
    }
}
