<?php

namespace App\Services\Account;

use App\Enums\Account\DataExportStatus;
use App\Jobs\Account\BuildDataExportJob;
use App\Models\Account\DataExport;
use App\Models\Agents\AgentMemory;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Agents\KnowledgeSource;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\WorkspaceMember;
use App\Notifications\Account\DataExportReadyNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Builds the "download my data" archive: a ZIP of JSON files holding what the
 * account itself owns (profile, security history, memberships, notifications,
 * referrals) plus the content the person personally authored or holds
 * (workflows they created, their agent conversations and memories, their
 * private knowledge sources). Workspace-wide data that belongs to the team
 * rather than to one person is left out.
 */
class DataExportService
{
    /**
     * How long a finished archive stays downloadable.
     */
    public const int RETENTION_DAYS = 7;

    /**
     * Queues a new export, or returns the one already in flight so a double
     * click does not build two archives.
     */
    public function request(User $user): DataExport
    {
        $inFlight = $user->dataExports()
            ->whereIn('status', [DataExportStatus::Pending, DataExportStatus::Processing])
            ->latest()
            ->first();

        if ($inFlight !== null) {
            return $inFlight;
        }

        $export = $user->dataExports()->create(['status' => DataExportStatus::Pending]);

        BuildDataExportJob::dispatch($export->id)->afterCommit();

        return $export;
    }

    public function build(DataExport $export): void
    {
        $export->update(['status' => DataExportStatus::Processing, 'started_at' => now(), 'error' => null]);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'data-export-');

        try {
            $this->writeArchive($export->user, $temporaryPath);

            $disk = (string) config('filesystems.default');
            $path = 'data-exports/'.$export->user_id.'/'.$export->id.'.zip';

            $stream = fopen($temporaryPath, 'rb');
            Storage::disk($disk)->put($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $export->update([
                'status' => DataExportStatus::Completed,
                'disk' => $disk,
                'path' => $path,
                'size_bytes' => filesize($temporaryPath) ?: null,
                'completed_at' => now(),
                'expires_at' => now()->addDays(self::RETENTION_DAYS),
            ]);

            $export->user->notify(new DataExportReadyNotification($export));
        } catch (Throwable $exception) {
            $this->markFailed($export, $exception);

            throw $exception;
        } finally {
            @unlink($temporaryPath);
        }
    }

    public function markFailed(DataExport $export, Throwable $exception): void
    {
        $export->update([
            'status' => DataExportStatus::Failed,
            'error' => Str::limit($exception->getMessage(), 1000),
            'completed_at' => now(),
        ]);
    }

    /**
     * Removes the files of every export past its download window.
     *
     * @return int The number of exports expired.
     */
    public function pruneExpired(): int
    {
        $count = 0;

        DataExport::query()->expired()->each(function (DataExport $export) use (&$count): void {
            $export->deleteFile();
            $export->update(['status' => DataExportStatus::Expired, 'path' => null]);
            $count++;
        });

        return $count;
    }

    private function writeArchive(User $user, string $path): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the export archive.');
        }

        foreach ($this->sections($user) as $name => $data) {
            $zip->addFromString($name.'.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        $zip->addFromString('README.txt', $this->readme($user));

        if ($zip->close() !== true) {
            throw new RuntimeException('Could not write the export archive.');
        }
    }

    /**
     * Every file in the archive, keyed by name (without extension).
     *
     * @return array<string, array<mixed>>
     */
    private function sections(User $user): array
    {
        $sessionIds = AgentSession::query()->where('user_id', $user->id)->pluck('id');

        return [
            'profile' => $this->profile($user),
            'workspaces' => $this->rows(
                WorkspaceMember::query()->where('user_id', $user->id)->with('workspace:id,name,slug,owner_id'),
                fn (WorkspaceMember $member): array => [
                    'workspace_id' => $member->workspace_id,
                    'workspace_name' => $member->workspace?->name,
                    'workspace_slug' => $member->workspace?->slug,
                    'is_owner' => $member->workspace?->owner_id === $user->id,
                    'role' => $member->role,
                    'joined_at' => $member->joined_at,
                ],
            ),
            'security/sign_in_methods' => $this->rows(
                $user->oauthConnections()->getQuery(),
                fn (Model $connection): array => $connection->only(['provider', 'created_at']),
            ),
            'security/activity' => $this->rows(
                $user->authEvents()->getQuery()->oldest(),
                fn (Model $event): array => $event->only(['event', 'ip_address', 'user_agent', 'created_at']),
            ),
            'notifications/inbox' => $this->rows(
                $user->notifications()->getQuery()->reorder()->oldest(),
                fn (Model $notification): array => $notification->only(['type', 'data', 'read_at', 'created_at']),
            ),
            'notifications/preferences' => [
                'account' => $user->accountNotificationPreferences()->get()->map->makeHidden(['id', 'user_id'])->all(),
                'workspace' => $user->notificationPreferences()->get()->map->makeHidden(['id', 'user_id'])->all(),
            ],
            'referrals' => [
                'code' => $user->referralCode?->only(['code', 'created_at']),
                'referred_by' => $user->referral?->only(['status', 'created_at']),
                'referrals_made' => $user->referralsMade()->get()->map->only(['status', 'created_at'])->all(),
                'rewards' => $user->referralRewards()->get()->map->makeHidden(['recipient_user_id'])->all(),
            ],
            'content/workflows' => $this->rows(
                Workflow::query()->where('created_by', $user->id),
                fn (Workflow $workflow): array => $workflow->only(['id', 'workspace_id', 'name', 'description', 'input_schema', 'created_at', 'updated_at']),
            ),
            'content/agent_conversations' => $this->rows(
                AgentSession::query()->whereKey($sessionIds),
                fn (AgentSession $session): array => $session->only(['id', 'workspace_id', 'agent_id', 'title', 'created_at', 'updated_at']),
            ),
            'content/agent_messages' => $this->rows(
                AgentMessage::query()->whereIn('agent_session_id', $sessionIds),
                fn (AgentMessage $message): array => $message->only(['agent_session_id', 'role', 'content', 'created_at']),
            ),
            'content/agent_memories' => $this->rows(
                AgentMemory::query()->where('user_id', $user->id),
                fn (AgentMemory $memory): array => $memory->only(['agent_id', 'key', 'value', 'type', 'created_at', 'updated_at']),
            ),
            'content/knowledge_sources' => $this->rows(
                KnowledgeSource::query()->where('owner_id', $user->id),
                fn (KnowledgeSource $source): array => $source->only(['id', 'workspace_id', 'name', 'type', 'collection', 'documents_count', 'last_synced_at', 'created_at']),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'timezone' => $user->timezone,
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'job_role' => $user->job_role,
            'discovery_source' => $user->discovery_source,
            'onboarding_completed_at' => $user->onboarding_completed_at,
            'marketing_opted_out_at' => $user->marketing_opted_out_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): array<string, mixed>  $map
     * @return array<int, array<string, mixed>>
     */
    private function rows(Builder $query, callable $map): array
    {
        return $query->lazy(500)->map($map)->values()->all();
    }

    private function readme(User $user): string
    {
        return implode("\n", [
            'Personal data export for '.$user->email,
            'Generated at '.now()->toIso8601String().' by '.config('app.name').'.',
            '',
            'Each .json file holds one kind of data:',
            '- profile.json: your account details',
            '- workspaces.json: the workspaces you belong to and your role in each',
            '- security/: sign-in methods and account security activity',
            '- notifications/: your inbox and notification settings',
            '- referrals.json: your referral code, referrals and rewards',
            '- content/: workflows you created, your agent conversations and memories, and your private knowledge sources',
            '',
            'Data that belongs to a workspace as a whole (run history, connectors, shared settings) is not included.',
        ])."\n";
    }
}
