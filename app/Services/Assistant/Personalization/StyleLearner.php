<?php

namespace App\Services\Assistant\Personalization;

use App\Actions\Billing\DeductCreditsAction;
use App\Ai\Assistant\StyleLearnerAgent;
use App\Ai\ResponseUsage;
use App\Enums\Assistant\AssistantFeedbackStatus;
use App\Enums\Assistant\AssistantStyleKind;
use App\Enums\Assistant\AssistantStyleSource;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Assistant\AssistantFeedback;
use App\Services\Assistant\Runtime\AssistantModel;
use App\Services\Billing\CreditMeter;

/**
 * Turns one piece of feedback into a lasting Tone or Design change, or
 * decides it isn't one and leaves the profiles alone.
 */
class StyleLearner
{
    public function __construct(
        private readonly StyleProfiles $profiles,
        private readonly AssistantModel $model,
        private readonly CreditMeter $meter,
        private readonly DeductCreditsAction $deductCredits,
    ) {}

    public function learn(AssistantFeedback $feedback): AssistantFeedback
    {
        $assistant = $feedback->assistant;
        $startedAt = now();
        [$provider, $model] = $this->model->for($assistant);

        $response = (new StyleLearnerAgent)->prompt($this->prompt($feedback), provider: $provider, model: $model);

        $this->deductCredits->execute(
            $assistant->workspace,
            CreditTransactionType::AssistantTurn,
            $feedback->id,
            $this->meter->costForAssistantTurn(ResponseUsage::from($response, $startedAt)),
            'Personal assistant learning from feedback',
            allowOverdraft: true,
        );

        $kind = AssistantStyleKind::tryFrom((string) ($response['kind'] ?? ''));

        if (($response['change'] ?? false) !== true || $kind === null || blank($response['notes'] ?? null)) {
            $feedback->forceFill(['status' => AssistantFeedbackStatus::Ignored])->save();

            return $feedback;
        }

        $profile = $this->profiles->update($assistant, $kind, (string) $response['notes'], AssistantStyleSource::Feedback, (string) ($response['reason'] ?? ''));

        $feedback->forceFill([
            'status' => AssistantFeedbackStatus::Applied,
            'applied_change' => ['kind' => $kind->value, 'version' => $profile->version, 'reason' => $response['reason'] ?? null],
        ])->save();

        return $feedback;
    }

    private function prompt(AssistantFeedback $feedback): string
    {
        $assistant = $feedback->assistant;

        return implode("\n\n", [
            "Current tone notes:\n".($this->profiles->body($assistant, AssistantStyleKind::Tone) ?? '(none yet)'),
            "Current design notes:\n".($this->profiles->body($assistant, AssistantStyleKind::Design) ?? '(none yet)'),
            "The reply:\n".mb_substr((string) $feedback->message->content, 0, 4000),
            'Rating: '.($feedback->rating->value === 'up' ? 'thumbs up' : 'thumbs down'),
            "Their comment:\n".$feedback->comment,
        ]);
    }
}
