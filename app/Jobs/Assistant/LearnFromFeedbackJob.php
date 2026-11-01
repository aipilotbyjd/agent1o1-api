<?php

namespace App\Jobs\Assistant;

use App\Enums\Assistant\AssistantFeedbackStatus;
use App\Models\Assistant\AssistantFeedback;
use App\Services\Assistant\Personalization\StyleLearner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class LearnFromFeedbackJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public AssistantFeedback $feedback)
    {
        $this->onConnection(config('assistant.runtime.queue_connection'));
        $this->onQueue(config('assistant.runtime.queue'));
    }

    public function handle(StyleLearner $learner): void
    {
        if ($this->feedback->refresh()->status !== AssistantFeedbackStatus::Pending) {
            return;
        }

        $learner->learn($this->feedback);
    }
}
