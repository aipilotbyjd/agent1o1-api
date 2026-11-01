<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Assistant\TranscribeAudioRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\Runtime\VoiceInput;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Voice input: the recording becomes text for the chat box. Only the
 * transcript comes back: the audio is never stored or sent to the model.
 */
class AssistantTranscriptionController extends Controller
{
    public function __invoke(TranscribeAudioRequest $request, Workspace $workspace, VoiceInput $voice): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        if (! $voice->isAvailable()) {
            return ApiResponse::error('Voice input is not set up.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return ApiResponse::success(['text' => $voice->transcribe($request->file('audio'))]);
    }
}
