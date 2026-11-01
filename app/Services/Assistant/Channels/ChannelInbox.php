<?php

namespace App\Services\Assistant\Channels;

use App\Enums\Assistant\AssistantSessionOrigin;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSession;
use App\Services\Assistant\Runtime\AssistantLoop;
use Illuminate\Support\Str;

/**
 * A message from outside the app becomes a turn in the conversation for
 * its thread: the same assistant, memory and tools as the web app.
 */
class ChannelInbox
{
    public function __construct(private readonly AssistantLoop $loop) {}

    /**
     * @param  array<string, mixed>  $context  what's needed to reply on the channel (Slack channel/thread, email headers)
     * @param  string|null  $title  for a new conversation, e.g. the email subject
     */
    public function receive(Assistant $assistant, AssistantSessionOrigin $origin, string $threadRef, string $text, array $context, ?string $title = null): AssistantSession
    {
        $session = $this->session($assistant, $origin, $threadRef, $context, $title);

        $this->loop->send($session, $text);

        return $session;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function session(Assistant $assistant, AssistantSessionOrigin $origin, string $threadRef, array $context, ?string $title = null): AssistantSession
    {
        $session = $assistant->sessions()->firstOrCreate(
            ['origin' => $origin, 'external_thread_ref' => mb_substr($threadRef, 0, 250)],
            ['last_activity_at' => now(), 'title' => blank($title) ? null : Str::limit($title, (int) config('assistant.limits.session_title_max_chars'))],
        );

        $session->forceFill(['channel_context' => [...($session->channel_context ?? []), ...$context]])->save();

        return $session;
    }
}
