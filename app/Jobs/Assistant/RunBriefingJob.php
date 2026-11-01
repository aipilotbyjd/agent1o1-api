<?php

namespace App\Jobs\Assistant;

use App\Enums\Assistant\AssistantBriefingType;
use App\Models\Assistant\AssistantBriefingRun;
use App\Services\Assistant\Briefings\BriefingRunner;
use App\Services\Assistant\Meetings\MeetingPrepRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunBriefingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public AssistantBriefingRun $run)
    {
        $this->timeout = (int) config('assistant.runtime.job_timeout');
        $this->onConnection(config('assistant.runtime.queue_connection'));
        $this->onQueue(config('assistant.runtime.queue'));
    }

    public function handle(BriefingRunner $daily, MeetingPrepRunner $meetingPrep): void
    {
        match ($this->run->config->type) {
            AssistantBriefingType::Daily => $daily->run($this->run),
            AssistantBriefingType::MeetingPrep => $meetingPrep->run($this->run),
        };
    }
}
