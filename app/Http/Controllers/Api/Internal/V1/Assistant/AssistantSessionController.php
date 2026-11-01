<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Enums\Assistant\AssistantMessageRole;
use App\Enums\Assistant\AssistantSessionOrigin;
use App\Enums\Assistant\AssistantTurnStatus;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Assistant\Concerns\ResolvesOwnAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Assistant\StoreAssistantSessionRequest;
use App\Http\Requests\Api\Internal\V1\Assistant\UpdateAssistantSessionRequest;
use App\Http\Resources\Api\Internal\V1\Assistant\AssistantMessageResource;
use App\Http\Resources\Api\Internal\V1\Assistant\AssistantSessionResource;
use App\Http\Resources\Api\Internal\V1\Assistant\AssistantTurnResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assistant\AssistantSession;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\Runtime\ContextMeter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Conversations with the member's own assistant. A session that belongs to
 * anyone else's assistant answers 404, not 403: its existence isn't
 * something another member should learn.
 */
class AssistantSessionController extends Controller
{
    use ResolvesOwnAssistant;

    private const int INCOGNITO_HOURS = 24;

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $sessions = $this->ownAssistant($request, $workspace)
            ->sessions()
            ->alive()
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->withCount('messages')
            ->latest('last_activity_at')
            ->latest()
            ->get();

        return ApiResponse::success([
            'sessions' => AssistantSessionResource::collection($sessions),
        ]);
    }

    public function store(StoreAssistantSessionRequest $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $incognito = $request->boolean('incognito');

        $session = $this->ownAssistant($request, $workspace)->sessions()->create([
            'title' => $request->validated('title'),
            'origin' => AssistantSessionOrigin::Web,
            'incognito' => $incognito,
            'expires_at' => $incognito ? now()->addHours(self::INCOGNITO_HOURS) : null,
            'last_activity_at' => now(),
        ]);

        return ApiResponse::created(['session' => AssistantSessionResource::make($session->refresh())], 'Session created successfully.');
    }

    public function show(Request $request, Workspace $workspace, AssistantSession $session): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);
        $this->ensureOwnSession($request, $workspace, $session);

        $activeTurn = $session->turns()
            ->whereIn('status', [AssistantTurnStatus::Queued, AssistantTurnStatus::Running, AssistantTurnStatus::AwaitingApproval])
            ->with('actions')
            ->latest()
            ->first();

        return ApiResponse::success([
            'session' => AssistantSessionResource::make($session->loadCount('messages')),
            'active_turn' => $activeTurn ? AssistantTurnResource::make($activeTurn) : null,
            'queued_count' => $session->queuedInputs()->whereNull('consumed_at')->count(),
        ]);
    }

    public function update(UpdateAssistantSessionRequest $request, Workspace $workspace, AssistantSession $session): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);
        $this->ensureOwnSession($request, $workspace, $session);

        $session->update($request->validated());

        return ApiResponse::success(['session' => AssistantSessionResource::make($session)], 'Session updated successfully.');
    }

    public function destroy(Request $request, Workspace $workspace, AssistantSession $session): Response
    {
        $this->requirePermission(Permission::AssistantUse);
        $this->ensureOwnSession($request, $workspace, $session);

        $session->delete();

        return ApiResponse::noContent();
    }

    public function messages(Request $request, Workspace $workspace, AssistantSession $session): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);
        $this->ensureOwnSession($request, $workspace, $session);

        $perPage = min(max($request->integer('per_page', 50), 1), 200);

        return ApiResponse::paginated(
            AssistantMessageResource::collection(
                $session->messages()
                    ->with('feedback')
                    ->whereIn('role', [AssistantMessageRole::User, AssistantMessageRole::Assistant])
                    ->oldest()
                    ->oldest('id')
                    ->paginate($perPage),
            ),
        );
    }

    public function context(Request $request, Workspace $workspace, AssistantSession $session, ContextMeter $meter): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);
        $this->ensureOwnSession($request, $workspace, $session);

        return ApiResponse::success(['context' => $meter->measure($session)]);
    }
}
