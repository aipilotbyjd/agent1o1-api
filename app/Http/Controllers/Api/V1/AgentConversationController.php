<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agent\ConversationMessageRequest;
use App\Http\Resources\V1\AgentConversationResource;
use App\Models\Agent;
use App\Models\Workspace;
use App\Services\AgentConversationService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Models\Conversation;
use Throwable;

class AgentConversationController extends Controller
{
    public function __construct(private readonly AgentConversationService $conversationService) {}

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

    public function store(ConversationMessageRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentRun)) {
            return $denied;
        }

        try {
            $result = $this->conversationService->startConversation(
                $agent,
                $request->user(),
                $request->validated('message'),
            );
        } catch (Throwable $e) {
            return $this->errorResponse($this->agentFailureMessage($e), 502);
        }

        return $this->successResponse('Conversation started.', $result, 201);
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

    public function sendMessage(ConversationMessageRequest $request, Workspace $workspace, Agent $agent, string $conversation): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentRun)) {
            return $denied;
        }

        if (! $this->resolveConversation($agent, $request->user()->id, $conversation)) {
            return $this->errorResponse('Conversation not found.', 404);
        }

        try {
            $result = $this->conversationService->sendMessage(
                $agent,
                $request->user(),
                $conversation,
                $request->validated('message'),
            );
        } catch (Throwable $e) {
            return $this->errorResponse($this->agentFailureMessage($e), 502);
        }

        return $this->successResponse('Message sent.', $result);
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
     * Provider HTTP failures (bad model id, bad/missing API key, rate limits) come back as
     * RequestException with the provider's own error body — surface that verbatim since it's
     * the only actionable detail; anything else stays generic to avoid leaking internals.
     */
    private function agentFailureMessage(Throwable $e): string
    {
        if ($e instanceof RequestException && $e->response) {
            $body = $e->response->json('error.message') ?? $e->response->body();

            return "The agent's model provider rejected the request: {$body}";
        }

        return 'The agent failed to respond. Please try again.';
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
