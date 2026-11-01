<?php

namespace App\Services\Assistant\Meetings;

use App\Enums\Assistant\AssistantBriefingType;
use App\Enums\Assistant\AssistantMeetingPrepStatus;
use App\Jobs\Assistant\RunBriefingJob;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantBriefingConfig;
use App\Models\Assistant\AssistantBriefingRun;
use App\Models\Assistant\AssistantMeeting;
use Illuminate\Support\Str;
use Throwable;

/**
 * Starts briefs for meetings that are about to begin (when automatic prep
 * is on), and on demand ("Prepare now", which works either way).
 */
class MeetingPrepScheduler
{
    public function __construct(private readonly MeetingSync $sync) {}

    public function config(Assistant $assistant): AssistantBriefingConfig
    {
        return $assistant->briefingConfigs()->firstOrCreate(
            ['type' => AssistantBriefingType::MeetingPrep],
            [
                'enabled' => false,
                'connector_scope' => 'all',
                'delivery' => ['email' => false],
                'settings' => ['auto' => true, 'minutes_before' => (int) config('assistant.meetings.default_minutes_before'), 'scope' => 'external_only'],
            ],
        );
    }

    /**
     * Syncs every enabled owner's calendar (run every few minutes).
     */
    public function syncAll(): int
    {
        $synced = 0;

        AssistantBriefingConfig::query()
            ->where('type', AssistantBriefingType::MeetingPrep)
            ->where('enabled', true)
            ->with('assistant.user')
            ->each(function (AssistantBriefingConfig $config) use (&$synced): void {
                try {
                    $this->sync->sync($config->assistant);
                    $synced++;
                } catch (Throwable $e) {
                    report($e);
                }
            });

        return $synced;
    }

    public function startDue(): int
    {
        $started = 0;

        AssistantBriefingConfig::query()
            ->where('type', AssistantBriefingType::MeetingPrep)
            ->where('enabled', true)
            ->whereNull('paused_at')
            ->each(function (AssistantBriefingConfig $config) use (&$started): void {
                if (($config->settings['auto'] ?? true) !== true) {
                    return;
                }

                $minutes = (int) ($config->settings['minutes_before'] ?? config('assistant.meetings.default_minutes_before'));

                $config->assistant->meetings()
                    ->where('prep_status', AssistantMeetingPrepStatus::None)
                    ->where('starts_at', '>', now())
                    ->where('starts_at', '<=', now()->addMinutes($minutes))
                    ->when(($config->settings['scope'] ?? 'external_only') === 'external_only', fn ($query) => $query->where('is_external', true))
                    ->each(function (AssistantMeeting $meeting) use ($config, &$started): void {
                        if ($this->start($config, $meeting, "meeting:{$meeting->provider_event_id}:{$meeting->starts_at->getTimestamp()}", 'schedule') !== null) {
                            $started++;
                        }
                    });
            });

        return $started;
    }

    public function prepareNow(AssistantMeeting $meeting): ?AssistantBriefingRun
    {
        return $this->start($this->config($meeting->assistant), $meeting, 'meeting-manual:'.Str::uuid(), 'manual');
    }

    private function start(AssistantBriefingConfig $config, AssistantMeeting $meeting, string $runKey, string $trigger): ?AssistantBriefingRun
    {
        $run = $config->runs()->firstOrCreate(
            ['run_key' => $runKey],
            ['assistant_meeting_id' => $meeting->id, 'trigger' => $trigger, 'window_start' => now()],
        );

        if (! $run->wasRecentlyCreated) {
            return null;
        }

        $meeting->forceFill(['prep_status' => AssistantMeetingPrepStatus::Preparing])->save();

        RunBriefingJob::dispatch($run);

        return $run;
    }
}
