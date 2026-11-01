<?php

namespace App\Services\Assistant\Channels;

use App\Enums\Assistant\AssistantSessionOrigin;
use App\Enums\Assistant\AssistantTurnStatus;
use App\Models\Assistant\AssistantSession;
use App\Models\Assistant\AssistantTurn;
use Throwable;

/**
 * Sends a finished turn back where the message came from. Approvals are
 * never decided over email, Slack or text: the reply links to the app instead.
 */
class ChannelReplies
{
    public function __construct(
        private readonly EmailChannel $email,
        private readonly SlackChannel $slack,
        private readonly SmsChannel $sms,
    ) {}

    public function deliver(AssistantTurn $turn): void
    {
        $session = $turn->session;

        if (! in_array($session?->origin, [AssistantSessionOrigin::Email, AssistantSessionOrigin::Slack, AssistantSessionOrigin::Sms], true)) {
            return;
        }

        $text = $this->text($turn);

        if ($text === null) {
            return;
        }

        $this->say($session, $text);
    }

    /**
     * Posts a message into a conversation on the channel it started on.
     * Never throws: a channel that is down only means the message is lost.
     */
    public function say(AssistantSession $session, string $text): void
    {
        try {
            match ($session->origin) {
                AssistantSessionOrigin::Email => $this->email->reply($session, $text),
                AssistantSessionOrigin::Slack => $this->slack->reply($session, $text),
                AssistantSessionOrigin::Sms => $this->sms->reply($session, $text),
                default => null,
            };
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function text(AssistantTurn $turn): ?string
    {
        $reply = trim((string) $turn->assistantMessage?->content);
        $link = config('app.frontend_url')."/{$turn->session->assistant->workspace_id}/assistant?session={$turn->assistant_session_id}";

        return match ($turn->status) {
            AssistantTurnStatus::Completed => $reply !== '' ? $reply : null,
            AssistantTurnStatus::AwaitingApproval => trim($reply."\n\nThis needs your OK before I go ahead. Approve or decline it here: {$link}"),
            AssistantTurnStatus::Failed => (string) ($turn->error ?: 'Something went wrong while answering. Please try again.'),
            default => null,
        };
    }
}
