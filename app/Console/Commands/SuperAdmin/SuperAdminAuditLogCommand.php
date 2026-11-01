<?php

namespace App\Console\Commands\SuperAdmin;

use App\Console\Commands\SuperAdmin\Concerns\InteractsWithSuperAdmins;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use Illuminate\Console\Command;

class SuperAdminAuditLogCommand extends Command
{
    use InteractsWithSuperAdmins;

    protected $signature = 'super-admin:audit-log {--limit=25 : How many entries to show} {--action= : Only actions starting with this prefix}';

    protected $description = 'Show the most recent Super Admin audit log entries';

    public function handle(): int
    {
        if (! $this->superAdminEnabled()) {
            return self::FAILURE;
        }

        $logs = SuperAdminAuditLog::query()
            ->with('admin:id,email')
            ->when($this->option('action'), fn ($query, string $action) => $query->where('action', 'like', $action.'%'))
            ->orderByDesc('created_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        $this->table(
            ['When', 'Actor', 'Action', 'Subject', 'Reason'],
            $logs->map(fn (SuperAdminAuditLog $log): array => [
                $log->created_at?->toDateTimeString(),
                $log->admin?->email ?? $log->actor_label ?? '—',
                $log->action,
                $log->subject_type !== null ? "{$log->subject_type}:{$log->subject_id}" : '—',
                $log->reason ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
