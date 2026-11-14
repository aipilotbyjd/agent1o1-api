<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Backups;

use App\Enums\System\BackupStatus;
use App\Enums\System\BackupType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Backups\DownloadBackupRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Backups\BackupResource;
use App\Http\Responses\ApiResponse;
use App\Models\System\Backup;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use App\Services\System\Backups\BackupService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Lists database backups, starts one, and hands out short-lived download
 * links. There is no update or delete action: backups are immutable.
 */
class BackupController extends Controller
{
    /** A completed backup older than this means the schedule has stopped working. */
    private const int HEALTHY_WITHIN_HOURS = 26;

    public function __construct(
        private readonly BackupService $backups,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(BackupStatus::class)],
            'type' => ['nullable', Rule::enum(BackupType::class)],
        ]);

        $backups = Backup::query()
            ->with('triggeredBy:id,name')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->latest()
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated(BackupResource::collection($backups));
    }

    public function show(Backup $backup)
    {
        return ApiResponse::success(['backup' => BackupResource::make($backup->load('triggeredBy:id,name'))]);
    }

    /**
     * What the panel's health card shows: is the schedule producing backups.
     */
    public function summary()
    {
        $lastCompleted = Backup::query()->where('status', BackupStatus::Completed)->latest('finished_at')->first();
        $lastFailed = Backup::query()->where('status', BackupStatus::Failed)->latest('finished_at')->first();

        $healthy = $lastCompleted?->finished_at?->greaterThan(now()->subHours(self::HEALTHY_WITHIN_HOURS)) ?? false;

        return ApiResponse::success([
            'enabled' => (bool) config('backups.enabled'),
            'healthy' => $healthy,
            'disk' => config('backups.disk'),
            'last_completed' => $lastCompleted === null ? null : BackupResource::make($lastCompleted),
            'last_failed' => $lastFailed === null ? null : BackupResource::make($lastFailed),
            'active' => Backup::query()->whereIn('status', [BackupStatus::Pending, BackupStatus::Running])->exists(),
            'completed_count' => Backup::query()->where('status', BackupStatus::Completed)->count(),
            'total_size_bytes' => (int) Backup::query()->where('status', BackupStatus::Completed)->sum('size_bytes'),
            'nightly_at' => config('backups.schedule.nightly_at'),
            'hourly' => (bool) config('backups.schedule.hourly'),
        ]);
    }

    /**
     * "Back up now": records a pending backup and queues it. Returns at once;
     * poll the backup (or the list) for its status.
     */
    public function store(Request $request)
    {
        abort_unless(config('backups.enabled'), 409, 'Backups are not enabled on this server.');

        $actor = $request->user('super-admin');
        $backup = $this->backups->queue(BackupType::Manual, $actor);

        abort_if($backup === null, 409, 'A backup is already queued or running.');

        $this->audit->record($actor, 'backups.requested', $backup, null, ['type' => $backup->type->value]);

        return ApiResponse::success(['backup' => BackupResource::make($backup)], 'Backup queued.', 202);
    }

    /**
     * A pre-signed link that works for a few minutes. Needs a reason and a
     * fresh authenticator code, and is always audited.
     */
    public function downloadLink(DownloadBackupRequest $request, Backup $backup)
    {
        try {
            $url = $this->backups->downloadUrl($backup);
        } catch (RuntimeException $exception) {
            abort(409, $exception->getMessage());
        }

        $expiresInMinutes = (int) config('backups.download_url_ttl_minutes');

        $this->audit->record(
            $request->user('super-admin'),
            'backups.download-link-issued',
            $backup,
            null,
            ['filename' => basename($backup->path), 'expires_in_minutes' => $expiresInMinutes],
            $request->string('reason')->toString(),
        );

        return ApiResponse::success([
            'url' => $url,
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ], 'Download link issued.');
    }
}
