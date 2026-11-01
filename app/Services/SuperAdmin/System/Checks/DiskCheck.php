<?php

namespace App\Services\SuperAdmin\System\Checks;

class DiskCheck implements HealthCheck
{
    public function name(): string
    {
        return 'disk';
    }

    public function run(): array
    {
        $path = (string) config('super_admin_health.disk.path');
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || $total === false || $total <= 0) {
            return ['status' => self::SKIPPED, 'message' => 'Disk space could not be read.'];
        }

        $percent = round($free / $total * 100, 1);
        $details = ['free_percent' => $percent, 'free_bytes' => (int) $free, 'total_bytes' => (int) $total];

        return match (true) {
            $percent <= config('super_admin_health.disk.free_percent_critical') => ['status' => self::DOWN, 'message' => "Only {$percent}% of the disk is free.", 'details' => $details],
            $percent <= config('super_admin_health.disk.free_percent_warning') => ['status' => self::DEGRADED, 'message' => "{$percent}% of the disk is free.", 'details' => $details],
            default => ['status' => self::HEALTHY, 'message' => "{$percent}% of the disk is free.", 'details' => $details],
        };
    }
}
