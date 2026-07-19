<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agent\ConversationMessageRequest;
use App\Http\Resources\V1\AgentConversationResource;
use App\Http\Resources\V1\AgentMessageRequestResource;
use App\Jobs\ProcessAgentMessageJob;
use App\Models\Agent;
use App\Models\AgentMessageRequest;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Models\Conversation;

class AgentConversationController extends Controller
{
    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentRun)) {
            return $denied;
        }

        $conversations = Conversation::query()
            ->where('agent_id', $agent->id)
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->paginate((int) $request->query('per_page', 25));

        return $this->paginatedResponse('Conversations retrieved.', AgentConversationResource::collection($conversations));
    }

    /** Starts a conversation by queuing its first message — the reply streams live over `agent.stream.{request_id}`. */
    public function store(ConversationMessageRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentRun)) {
            return $denied;
        }

        $pending = $this->queueMessage($agent, $request->user(), $request->validated('message'), null);

        return $this->successResponse('Message queued.', ['request_id' => $pending->id], 202);
    }

    public function show(Request $request, Workspace $workspace, Agent $agent, string $conversation): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentRun)) {
            return $denied;
        }

        $model = $this->resolveConversation($agent, $request->user()->id, $conversation);

        if (! $model) {
            return $this->errorResponse('Conversation not found.', 404);
        }

        return $this->successResponse(
            'Conversation retrieved.',
            new AgentConversationResource($model->load(['messages' => fn ($q) => $q->oldest()])),
        );
    }

    /** Continues a conversation by queuing the next message — the reply streams live over `agent.stream.{request_id}`. */
    public function sendMessage(ConversationMessageRequest $request, Workspace $workspace, Agent $agent, string $conversation): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentRun)) {
            return $denied;
        }

        if (! $this->resolveConversation($agent, $request->user()->id, $conversation)) {
            return $this->errorResponse('Conversation not found.', 404);
        }

        $pending = $this->queueMessage($agent, $request->user(), $request->validated('message'), $conversation);

        return $this->successResponse('Message queued.', ['request_id' => $pending->id], 202);
    }

    public function destroy(Request $request, Workspace $workspace, Agent $agent, string $conversation): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentRun)) {
            return $denied;
        }

        $model = $this->resolveConversation($agent, $request->user()->id, $conversation);

        if (! $model) {
            return $this->errorResponse('Conversation not found.', 404);
        }

        $model->messages()->delete();
        $model->delete();

        return $this->successResponse('Conversation deleted.');
    }

    /**
     * Polls the status of a queued message request for clients that can't hold
     * the `agent.stream.{request_id}` WebSocket — returns pending/processing/
     * completed/failed plus the resolved conversation and run ids.
     */
    public function requestStatus(Request $request, Workspace $workspace, Agent $agent, string $requestId): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentRun)) {
            return $denied;
        }

        $messageRequest = AgentMessageRequest::query()
            ->whereKey($requestId)
            ->where('agent_id', $agent->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $messageRequest) {
            return $this->errorResponse('Message request not found.', 404);
        }

        return $this->successResponse('Message request retrieved.', new AgentMessageRequestResource($messageRequest));
    }

    /**
     * Creates the ownership-checkable tracking row (also doubles as the
     * `agent.stream.{id}` channel key — see routes/channels.php) and dispatches
     * the job that actually streams the reply.
     */
    private function queueMessage(Agent $agent, $user, string $message, ?string $conversationId): AgentMessageRequest
    {
        $pending = AgentMessageRequest::create([
            'agent_id' => $agent->id,
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'status' => 'pending',
        ]);

        ProcessAgentMessageJob::dispatch($agent, $user, $message, $pending);

        return $pending;
    }

    private function resolveConversation(Agent $agent, int $userId, string $conversationId): ?Conversation
    {
        return Conversation::query()
            ->whereKey($conversationId)
            ->where('agent_id', $agent->id)
            ->where('user_id', $userId)
            ->first();
    }
}
