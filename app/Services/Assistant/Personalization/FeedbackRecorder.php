<?php

namespace App\Services\Assistant\Personalization;

use App\Enums\Assistant\AssistantFeedbackRating;
use App\Enums\Assistant\AssistantFeedbackStatus;
use App\Jobs\Assistant\LearnFromFeedbackJob;
use App\Models\Assistant\AssistantFeedback;
use App\Models\Assistant\AssistantMessage;

/**
 * One rating per reply; rating again replaces it. Only a comment can teach
 * a lasting preference, and never from an incognito conversation.
 */
class FeedbackRecorder
{
    public function record(AssistantMessage $message, AssistantFeedbackRating $rating, ?string $comment): AssistantFeedback
    {
        $session = $message->session;
        $learns = filled($comment) && ! $session->incognito;

        $feedback = AssistantFeedback::query()->updateOrCreate(
            ['assistant_message_id' => $message->id],
            [
                'assistant_id' => $session->assistant_id,
                'rating' => $rating,
                'comment' => filled($comment) ? trim($comment) : null,
                'status' => $learns ? AssistantFeedbackStatus::Pending : AssistantFeedbackStatus::Ignored,
                'applied_change' => null,
            ],
        );

        if ($learns) {
            LearnFromFeedbackJob::dispatch($feedback);
        }

        return $feedback->refresh();
    }
}
