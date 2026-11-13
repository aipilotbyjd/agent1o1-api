<?php

namespace App\Jobs\Analytics;

use App\Enums\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PostHog\PostHog;

/**
 * Sends one event to PostHog. Retries a few times because PostHog or the
 * network can be briefly unavailable; an event that never lands is dropped.
 */
class CapturePostHogEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    /**
     * @param  array<string, scalar|null>  $properties
     */
    public function __construct(
        public readonly string $distinctId,
        public readonly string $event,
        public readonly array $properties = [],
        public readonly ?string $workspaceId = null,
    ) {
        $this->onQueue(Queue::Notification->value);
    }

    public function handle(): void
    {
        PostHog::init(
            (string) config('posthog.project_token'),
            [
                'host' => (string) config('posthog.host'),
                'timeout' => (int) config('posthog.timeout') * 1000,
            ],
        );

        $message = [
            'distinctId' => $this->distinctId,
            'event' => $this->event,
            'properties' => $this->properties,
        ];

        if ($this->workspaceId !== null) {
            $message['groups'] = ['workspace' => $this->workspaceId];
        }

        PostHog::capture($message);
        PostHog::flush();
    }
}
