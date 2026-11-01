<?php

namespace App\Services\Assistant\Runtime;

use App\Actions\Billing\DeductCreditsAction;
use App\Ai\Assistant\RecapAgent;
use App\Ai\ResponseUsage;
use App\Enums\Assistant\AssistantMessageRole;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Assistant\AssistantMessage;
use App\Models\Assistant\AssistantSession;
use App\Services\Assistant\AssistantInstructions;
use App\Services\Billing\CreditMeter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a long conversation inside the model's context window: once it
 * grows past a message count or a share of the window, everything but the
 * most recent messages is summarized into a `Recap` message. The originals
 * stay in the transcript (marked `compacted_into_id`) so the owner still
 * sees them; only the model stops being sent them.
 */
class ContextCompactor
{
    public function __construct(
        private readonly AssistantModel $model,
        private readonly AssistantInstructions $instructions,
        private readonly CreditMeter $meter,
        private readonly DeductCreditsAction $deductCredits,
    ) {}

    public function needsCompaction(AssistantSession $session): bool
    {
        if ($session->incognito) {
            return false;
        }

        $live = $this->liveMessages($session);

        if ($live->count() <= (int) config('assistant.context.keep_recent_messages')) {
            return false;
        }

        $limitTokens = $this->model->windowTokens($session->assistant) * (float) config('assistant.context.compact_at_ratio');

        return $live->count() > (int) config('assistant.context.compact_after_messages')
            || $this->estimateTokens($live->sum(fn (AssistantMessage $message): int => mb_strlen((string) $message->content))) > $limitTokens;
    }

    /**
     * Summarizes everything except the recent tail into a new recap.
     * Returns the recap, or null if there was nothing old enough to fold.
     */
    public function compact(AssistantSession $session): ?AssistantMessage
    {
        $live = $this->liveMessages($session);
        $older = $this->olderPart($live);

        if ($older->isEmpty()) {
            return null;
        }

        $startedAt = now();
        $previous = $this->instructions->recapFor($session);
        [$provider, $model] = $this->model->for($session->assistant);

        $response = (new RecapAgent)->prompt($this->transcript($older, $previous), provider: $provider, model: $model);

        $usage = ResponseUsage::from($response, $startedAt);

        $recap = DB::transaction(function () use ($session, $older, $response, $usage): AssistantMessage {
            $recap = $session->messages()->create([
                'role' => AssistantMessageRole::Recap,
                'content' => trim($response->text),
                'usage' => $usage,
            ]);

            AssistantMessage::query()->whereKey($older->modelKeys())->update(['compacted_into_id' => $recap->id]);

            return $recap;
        });

        $this->deductCredits->execute(
            $session->assistant->workspace,
            CreditTransactionType::AssistantTurn,
            $recap->id,
            $this->meter->costForAssistantTurn($usage),
            'Personal assistant conversation summary',
            allowOverdraft: true,
        );

        return $recap;
    }

    public function estimateTokens(int $characters): int
    {
        return (int) ceil($characters / max(1, (int) config('assistant.context.chars_per_token')));
    }

    /**
     * User and assistant messages still sent to the model, oldest first.
     *
     * @return Collection<int, AssistantMessage>
     */
    private function liveMessages(AssistantSession $session): Collection
    {
        return $session->messages()
            ->whereIn('role', [AssistantMessageRole::User, AssistantMessageRole::Assistant])
            ->whereNull('compacted_into_id')
            ->whereNull('paused_state')
            ->oldest()
            ->oldest('id')
            ->get();
    }

    /**
     * All but the kept tail, and the tail always starts on a user
     * message, so the model never sees a reply without its question.
     *
     * @param  Collection<int, AssistantMessage>  $live
     * @return Collection<int, AssistantMessage>
     */
    private function olderPart(Collection $live): Collection
    {
        $cut = max(0, $live->count() - (int) config('assistant.context.keep_recent_messages'));

        while ($cut > 0 && $live->get($cut)?->role !== AssistantMessageRole::User) {
            $cut--;
        }

        return $live->take($cut)->values();
    }

    /**
     * @param  Collection<int, AssistantMessage>  $messages
     */
    private function transcript(Collection $messages, ?string $previousRecap): string
    {
        $lines = $messages->map(fn (AssistantMessage $message): string => ($message->role === AssistantMessageRole::User ? 'Person' : 'Assistant')
            .': '.trim((string) $message->content));

        $previous = filled($previousRecap) ? "Summary of what came before:\n{$previousRecap}\n\n" : '';

        return $previous."Conversation to summarize:\n".$lines->implode("\n\n");
    }
}
