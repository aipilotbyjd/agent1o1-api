<?php

namespace App\Console\Commands\Broadcasts;

use App\Events\Broadcasts\AnnouncementsChanged;
use App\Models\Broadcasts\Announcement;
use App\Services\Broadcasts\BroadcastCampaignService;
use Illuminate\Console\Command;

/**
 * Run every minute. Does the three things that happen by the clock rather than
 * by a request: starts scheduled email campaigns whose time has come, resumes
 * campaigns whose sending stalled, and nudges open apps when an announcement
 * has just started or ended so banners appear and disappear without a reload.
 */
class DispatchDueBroadcastsCommand extends Command
{
    protected $signature = 'broadcasts:dispatch-due';

    protected $description = 'Starts due email campaigns, resumes stalled ones and pushes announcement window changes.';

    public function handle(BroadcastCampaignService $campaigns): int
    {
        $started = $campaigns->startDue();
        $resumed = $campaigns->resumeStalled();

        $this->info("Started {$started} campaign(s), resumed {$resumed}.");

        if ($this->announcementWindowChangedLastMinute()) {
            AnnouncementsChanged::dispatch('window');
        }

        return self::SUCCESS;
    }

    private function announcementWindowChangedLastMinute(): bool
    {
        $since = now()->subMinute();

        return Announcement::query()
            ->whereNotNull('published_at')
            ->where(fn ($query) => $query
                ->whereBetween('starts_at', [$since, now()])
                ->orWhereBetween('ends_at', [$since, now()]))
            ->exists();
    }
}
