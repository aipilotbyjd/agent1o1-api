<?php

namespace App\Engine;

use Carbon\CarbonInterface;

readonly class ExecutionPause
{
    public function __construct(
        public string $reason,
        public CarbonInterface $resumeAt,
        public array $nodeOutput = [],
        public ?string $webhookWaitUuid = null,
    ) {}

    public static function forDelay(CarbonInterface $resumeAt, array $output = []): self
    {
        return new self(
            reason: 'delay',
            resumeAt: $resumeAt,
            nodeOutput: $output,
        );
    }

    public static function forWebhookWait(CarbonInterface $resumeAt, string $uuid): self
    {
        return new self(
            reason: 'webhook_wait',
            resumeAt: $resumeAt,
            webhookWaitUuid: $uuid,
        );
    }

    public static function forEvent(CarbonInterface $resumeAt): self
    {
        return new self(
            reason: 'wait_event',
            resumeAt: $resumeAt,
        );
    }
}
