<?php

namespace App\Services\Notifications;

/**
 * What a Slack, Discord or webhook channel receives for one notification,
 * before it is shaped for that channel type.
 */
final readonly class IntegrationMessage
{
    /**
     * @param  array<int, mixed>|null  $slackBlocks  Interactive Slack content (e.g. approve/reject buttons); `text()` stays the fallback Slack shows in its own notifications.
     */
    public function __construct(
        public string $title,
        public ?string $body = null,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
        public ?array $slackBlocks = null,
    ) {}

    /**
     * The plain-text form every channel type can show.
     */
    public function text(): string
    {
        $text = $this->body === null || $this->body === '' ? $this->title : "{$this->title}: {$this->body}";

        return $this->actionUrl === null ? $text : "{$text}\n{$this->actionUrl}";
    }
}
