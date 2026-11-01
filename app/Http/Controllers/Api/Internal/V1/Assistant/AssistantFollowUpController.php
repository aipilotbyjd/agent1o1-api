<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Enums\Assistant\AssistantFollowUpStatus;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Assistant\Concerns\ResolvesOwnAssistant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Assistant\AssistantFollowUpResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assistant\AssistantFollowUp;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The email threads the assistant is watching for a reply, and the ones it
 * recently settled. The owner can stop watching a waiting one.
 */
class AssistantFollowUpController extends Controller
{
    use ResolvesOwnAssistant;

    /**
     * Waiting follow-ups first (soonest date first), then the latest settled ones.
     */
    private const int SETTLED_SHOWN = 20;

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $assistant = $this->ownAssistant($request, $workspace);

        $waiting = $assistant->followUps()->where('status', AssistantFollowUpStatus::Pending)->orderBy('remind_at')->get();
        $settled = $assistant->followUps()->where('status', '!=', AssistantFollowUpStatus::Pending)->latest('resolved_at')->limit(self::SETTLED_SHOWN)->get();

        return ApiResponse::success(['follow_ups' => AssistantFollowUpResource::collection($waiting->concat($settled))]);
    }

    public function destroy(Request $request, Workspace $workspace, AssistantFollowUp $followUp): Response
    {
        $this->requirePermission(Permission::AssistantUse);

        abort_if($followUp->assistant_id !== $this->ownAssistant($request, $workspace)->id, 404);

        if ($followUp->isPending()) {
            $followUp->forceFill(['status' => AssistantFollowUpStatus::Cancelled, 'resolved_at' => now()])->save();
        }

        return ApiResponse::noContent();
    }
}
