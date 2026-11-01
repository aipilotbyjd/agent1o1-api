<?php

namespace App\Services\Assistant\Channels;

use App\Ai\Assistant\ThreadRouterAgent;
use App\Enums\Assistant\AssistantSessionOrigin;
use App\Enums\Assistant\AssistantTurnStatus;
use App\Enums\Billing\Feature;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantPhoneNumber;
use App\Models\Assistant\AssistantSession;
use App\Services\Assistant\Runtime\AssistantLoop;
use App\Services\Assistant\Runtime\AssistantModel;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Texts to the assistant's number. Only verified numbers are answered, and
 * each number is rate limited. A quick follow-up continues the last
 * conversation, a text days later starts a new one, and in between a small
 * model decides. Replies are plain text in a few parts, with a link to the
 * app when they run long.
 */
class SmsChannel
{
    public function __construct(
        private readonly ChannelUsers $users,
        private readonly ChannelInbox $inbox,
        private readonly AssistantLoop $loop,
        private readonly AssistantModel $model,
        private readonly TwilioSms $sms,
    ) {}

    /**
     * @return string what happened, for logs
     */
    public function handle(string $from, string $text): string
    {
        $number = AssistantPhoneNumber::query()->where('phone', $from)->whereNotNull('verified_at')->with('user')->first();

        if ($number === null) {
            return 'ignored: unknown number';
        }

        if (! $this->withinLimits($from)) {
            return 'ignored: rate limited';
        }

        $assistant = $this->users->assistantFor($number->user);
        $text = trim($text);

        if ($assistant === null || $text === '') {
            return 'ignored: no assistant';
        }

        if (! $assistant->workspace->currentPlan()?->hasFeature(Feature::AssistantSms)) {
            $this->sms->send($from, 'Texting needs a plan that includes it. Upgrade in the app: '.config('app.frontend_url'));

            return 'ignored: plan';
        }

        $latest = $this->latestSession($assistant, $from);

        if (Str::lower($text) === '!stop') {
            $running = $latest?->turns()->whereIn('status', AssistantTurnStatus::inFlightValues())->latest()->first();

            if ($running !== null) {
                $this->loop->cancel($running);
            }

            $this->sms->send($from, $running !== null ? 'Stopped.' : 'Nothing is running.');

            return 'stopped';
        }

        $session = $latest !== null && $this->continues($assistant, $latest, $text)
            ? $latest
            : null;

        $this->inbox->receive(
            $assistant,
            AssistantSessionOrigin::Sms,
            $session?->external_thread_ref ?? "sms:{$from}:".Str::uuid(),
            $text,
            ['phone' => $from],
            title: $session === null ? Str::limit($text, 60) : null,
        );

        return 'accepted';
    }

    public function reply(AssistantSession $session, string $text): void
    {
        $phone = $session->channel_context['phone'] ?? null;

        if (blank($phone)) {
            return;
        }

        foreach ($this->parts($this->plain($text), $session) as $part) {
            $this->sms->send($phone, $part);
        }
    }

    private function latestSession(Assistant $assistant, string $from): ?AssistantSession
    {
        return $assistant->sessions()
            ->where('origin', AssistantSessionOrigin::Sms)
            ->where('external_thread_ref', 'like', "sms:{$from}:%")
            ->latest('last_activity_at')
            ->first();
    }

    private function continues(Assistant $assistant, AssistantSession $session, string $text): bool
    {
        $last = $session->last_activity_at ?? $session->created_at;

        if ($last->gt(now()->subMinutes((int) config('assistant.channels.sms.continue_within_minutes')))) {
            return true;
        }

        if ($last->lt(now()->subDays((int) config('assistant.channels.sms.new_after_days')))) {
            return false;
        }

        $recent = $session->messages()->whereIn('role', ['user', 'assistant'])->latest()->limit(4)->get()->reverse()
            ->map(fn ($message): string => "{$message->role->value}: ".Str::limit((string) $message->content, 300))
            ->implode("\n");

        try {
            [$provider, $model] = $this->model->for($assistant);

            return (bool) (new ThreadRouterAgent)->prompt("Last conversation:\n{$recent}\n\nNew message:\n{$text}", provider: $provider, model: $model)['continues'];
        } catch (Throwable $e) {
            report($e);

            return true;
        }
    }

    private function withinLimits(string $from): bool
    {
        $minute = "assistant-sms-minute:{$from}";
        $hour = "assistant-sms-hour:{$from}";

        if (RateLimiter::tooManyAttempts($minute, (int) config('assistant.channels.sms.per_minute'))
            || RateLimiter::tooManyAttempts($hour, (int) config('assistant.channels.sms.per_hour'))) {
            return false;
        }

        RateLimiter::hit($minute, 60);
        RateLimiter::hit($hour, 3600);

        return true;
    }

    /**
     * Markdown → plain text: **bold**, headings and [text](url) links.
     */
    private function plain(string $text): string
    {
        $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text) ?? $text;
        $text = preg_replace('/^#{1,6}\s*/m', '', $text) ?? $text;

        return trim(preg_replace('/\[([^\]]+)\]\((https?:[^)\s]+)\)/', '$1 ($2)', $text) ?? $text);
    }

    /**
     * Up to `max_chunks` texts; past that, the rest is in the app.
     *
     * @return list<string>
     */
    private function parts(string $text, AssistantSession $session): array
    {
        $size = (int) config('assistant.channels.sms.chunk_chars');
        $max = (int) config('assistant.channels.sms.max_chunks');
        $parts = mb_str_split($text, $size) ?: [''];

        if (count($parts) <= $max) {
            return $parts;
        }

        $link = config('app.frontend_url')."/{$session->assistant->workspace_id}/assistant?session={$session->id}";
        $suffix = "… Read the rest: {$link}";
        $parts = array_slice($parts, 0, $max);
        $parts[$max - 1] = mb_substr($parts[$max - 1], 0, $size - mb_strlen($suffix)).$suffix;

        return $parts;
    }
}
