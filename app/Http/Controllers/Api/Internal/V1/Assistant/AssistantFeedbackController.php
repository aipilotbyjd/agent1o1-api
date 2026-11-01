<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Enums\Assistant\AssistantFeedbackRating;
use App\Enums\Assistant\AssistantMessageRole;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Assistant\Concerns\ResolvesOwnAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Assistant\StoreAssistantFeedbackRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Assistant\AssistantMessage;
use App\Models\Assistant\AssistantSession;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\Personalization\FeedbackRecorder;
use Illuminate\Http\JsonResponse;

class AssistantFeedbackController extends Controller
{
    use ResolvesOwnAssistant;

    public function store(StoreAssistantFeedbackRequest $request, Workspace $workspace, AssistantSession $session, AssistantMessage $message, FeedbackRecorder $recorder): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);
        $this->ensureOwnSession($request, $workspace, $session);

        abort_if($message->assistant_session_id !== $session->id || $message->role !== AssistantMessageRole::Assistant, 404);

        $feedback = $recorder->record($message, AssistantFeedbackRating::from($request->validated('rating')), $request->validated('comment'));

        return ApiResponse::success([
            'feedback' => [
                'rating' => $feedback->rating->value,
                'comment' => $feedback->comment,
                'status' => $feedback->status->value,
            ],
        ], 'Thanks for the feedback.');
    }
}
