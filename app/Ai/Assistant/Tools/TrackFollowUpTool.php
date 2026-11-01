<?php

namespace App\Ai\Assistant\Tools;

use App\Enums\Assistant\AssistantFollowUpStatus;
use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Starts watching an email thread: if nobody answers within the given days
 * the owner is reminded. It only reads the mailbox and notifies the owner,
 * so it counts as the assistant's own bookkeeping (`Internal`).
 */
class TrackFollowUpTool extends AssistantTool
{
    public const string NAME = 'track_follow_up';

    public function __construct(
        private readonly Assistant $assistant,
        private readonly ?AssistantSession $session = null,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function effect(): AssistantToolEffect
    {
        return AssistantToolEffect::Internal;
    }

    public function description(): Stringable|string
    {
        return 'Watches an email thread the person is waiting on and reminds them if nobody replies within the given number of days. '
            .'Needs the thread id from the email tool and works only while Smart Inbox is connected. '
            .'Tracking the same thread again moves its reminder date.';
    }

    protected function execute(Request $request): string
    {
        if (! ($this->assistant->inbox?->enabled ?? false)) {
            return 'Follow-ups need Smart Inbox: ask the person to connect and turn on their mailbox first.';
        }

        $threadId = trim((string) $request['thread_id']);

        if ($threadId === '') {
            return 'Nothing was tracked: a thread_id is needed.';
        }

        $days = max(1, min((int) ($request['days'] ?? 3), (int) config('assistant.follow_ups.max_days')));

        $pending = $this->assistant->followUps()->where('status', AssistantFollowUpStatus::Pending);
        $existing = (clone $pending)->where('thread_id', $threadId)->first();

        if ($existing === null && (clone $pending)->count() >= (int) config('assistant.follow_ups.max_pending')) {
            return 'Nothing was tracked: too many follow-ups are already waiting. Cancel some first.';
        }

        $attributes = [
            'remind_at' => now()->addDays($days),
            'subject' => Str::limit(Str::squish((string) ($request['subject'] ?? '')), 200, '') ?: null,
            'note' => Str::limit(Str::squish((string) ($request['note'] ?? '')), 500, '') ?: null,
            'assistant_session_id' => $this->session?->id,
        ];

        // The reminder only counts answers that arrive after tracking began.
        $followUp = $existing ?? $this->assistant->followUps()->make(['thread_id' => $threadId, 'status' => AssistantFollowUpStatus::Pending]);
        $followUp->fill($attributes)->save();

        return json_encode([
            'id' => $followUp->id,
            'remind_at' => $followUp->remind_at->toIso8601String(),
            'note' => "The person will be reminded in {$days} day(s) if nobody replies.",
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'thread_id' => $schema->string()->required(),
            'subject' => $schema->string()->description('The email subject, shown in the reminder.'),
            'days' => $schema->integer()->description('Days to wait for a reply. Defaults to 3.'),
            'note' => $schema->string()->description('Why it matters, shown in the reminder.'),
        ];
    }
}
