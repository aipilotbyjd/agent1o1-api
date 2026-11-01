<?php

namespace App\Services\Assistant\Channels;

use App\Enums\Assistant\AssistantSessionOrigin;
use App\Enums\Assistant\AssistantTurnStatus;
use App\Models\Assistant\AssistantSession;
use App\Models\Assistant\AssistantSlackInstall;
use App\Services\Assistant\Runtime\AssistantLoop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DMs to the platform's Slack app. The sender is matched to their account
 * by Slack email; a top-level DM starts a conversation and replies in its
 * thread continue it. `!stop` stops the running reply, `!link` links the
 * conversation in the app.
 */
class SlackChannel
{
    public function __construct(
        private readonly ChannelUsers $users,
        private readonly ChannelInbox $inbox,
        private readonly AssistantLoop $loop,
    ) {}

    /**
     * @param  array<string, mixed>  $event  a Slack `message` event in a DM
     */
    public function handle(string $teamId, array $event): void
    {
        $install = AssistantSlackInstall::query()->where('slack_team_id', $teamId)->first();

        if ($install === null) {
            return;
        }

        $channel = (string) $event['channel'];
        $threadTs = (string) ($event['thread_ts'] ?? $event['ts']);
        $text = trim((string) ($event['text'] ?? ''));

        $email = $this->email($install, (string) $event['user']);
        $user = $email === null ? null : $this->users->byEmail($email);
        $assistant = $user === null ? null : $this->users->assistantFor($user, $install->workspace);

        if ($assistant === null) {
            $this->post($install, $channel, $threadTs, "I couldn't match your Slack account to a member of this workspace. Sign up or ask an admin to invite you with the same email: ".config('app.frontend_url'));

            return;
        }

        $context = ['team_id' => $teamId, 'channel' => $channel, 'thread_ts' => $threadTs];

        if (Str::lower($text) === '!stop' || Str::lower($text) === '!link') {
            $session = $this->inbox->session($assistant, AssistantSessionOrigin::Slack, "{$teamId}:{$channel}:{$threadTs}", $context);
            $this->command(Str::lower($text), $session, $install, $channel, $threadTs);

            return;
        }

        if ($text === '') {
            return;
        }

        $this->inbox->receive($assistant, AssistantSessionOrigin::Slack, "{$teamId}:{$channel}:{$threadTs}", $text, $context);
    }

    public function reply(AssistantSession $session, string $text): void
    {
        $context = $session->channel_context ?? [];
        $install = AssistantSlackInstall::query()->where('slack_team_id', $context['team_id'] ?? null)->first();

        if ($install === null) {
            return;
        }

        $this->post($install, (string) $context['channel'], (string) $context['thread_ts'], $this->mrkdwn($text));
    }

    private function command(string $command, AssistantSession $session, AssistantSlackInstall $install, string $channel, string $threadTs): void
    {
        if ($command === '!link') {
            $this->post($install, $channel, $threadTs, config('app.frontend_url')."/{$install->workspace_id}/assistant?session={$session->id}");

            return;
        }

        $running = $session->turns()->whereIn('status', AssistantTurnStatus::inFlightValues())->latest()->first();

        if ($running !== null) {
            $this->loop->cancel($running);
        }

        $this->post($install, $channel, $threadTs, $running !== null ? 'Stopped.' : 'Nothing is running.');
    }

    private function email(AssistantSlackInstall $install, string $slackUserId): ?string
    {
        $response = Http::withToken($install->bot_token)->get('https://slack.com/api/users.info', ['user' => $slackUserId]);

        return $response->json('ok') === true ? $response->json('user.profile.email') : null;
    }

    private function post(AssistantSlackInstall $install, string $channel, string $threadTs, string $text): void
    {
        $response = Http::withToken($install->bot_token)->asJson()->post('https://slack.com/api/chat.postMessage', [
            'channel' => $channel,
            'thread_ts' => $threadTs,
            'text' => Str::limit($text, 39000),
        ]);

        if ($response->json('ok') !== true) {
            throw new RuntimeException('Slack chat.postMessage failed: '.($response->json('error') ?? $response->body()));
        }
    }

    /**
     * Markdown → Slack mrkdwn for the common cases: **bold**, [text](url),
     * and # headings.
     */
    private function mrkdwn(string $text): string
    {
        $text = preg_replace('/\*\*(.+?)\*\*/s', '*$1*', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\((https?:[^)\s]+)\)/', '<$2|$1>', $text) ?? $text;

        return preg_replace('/^#{1,6}\s*(.+)$/m', '*$1*', $text) ?? $text;
    }
}
