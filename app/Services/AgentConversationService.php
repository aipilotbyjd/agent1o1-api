<?php

namespace App\Services;

use App\Agents\AgentRunner;
use App\Models\Agent;
use App\Models\Credential;
use App\Models\User;
use Laravel\Ai\Models\Conversation;

class AgentConversationService
{
    public function __construct(private readonly AgentRunner $agentRunner) {}

    /**
     * Start a new conversation with the agent and return its id and first reply.
     *
     * @return array{conversation_id: ?string, response: string}
     */
    public function startConversation(Agent $agent, User $user, string $message): array
    {
        $workflowAgent = $this->agentRunner->build($agent, $message, [
            'agent' => $agent,
            'credentials' => $this->loadCredentials($agent),
        ]);

        $response = $workflowAgent->forUser($user)->prompt(
            $message,
            provider: $agent->provider,
            model: $agent->model,
        );

        $conversationId = $response->conversationId ?? $workflowAgent->currentConversation();

        $this->stampConversation($conversationId, $agent, $user);

        return [
            'conversation_id' => $conversationId,
            'response' => (string) $response,
        ];
    }

    /**
     * Continue an existing conversation.
     *
     * @return array{conversation_id: string, response: string}
     */
    public function sendMessage(Agent $agent, User $user, string $conversationId, string $message): array
    {
        $workflowAgent = $this->agentRunner->build($agent, $message, [
            'agent' => $agent,
            'credentials' => $this->loadCredentials($agent),
        ]);

        $response = $workflowAgent->continue($conversationId, as: $user)->prompt(
            $message,
            provider: $agent->provider,
            model: $agent->model,
        );

        return [
            'conversation_id' => $conversationId,
            'response' => (string) $response,
        ];
    }

    /**
     * Stamp the agent and workspace onto the freshly created conversation row so
     * it can be scoped per-agent in the API (laravel/ai only stores user + title).
     */
    private function stampConversation(?string $conversationId, Agent $agent, User $user): void
    {
        if ($conversationId === null) {
            return;
        }

        Conversation::query()
            ->whereKey($conversationId)
            ->update([
                'agent_id' => $agent->id,
                'workspace_id' => $agent->workspace_id,
            ]);
    }

    /**
     * Resolve the workspace credentials available to the agent, keyed by type.
     *
     * @return array<string, array<string, mixed>>
     */
    private function loadCredentials(Agent $agent): array
    {
        return Credential::query()
            ->where('workspace_id', $agent->workspace_id)
            ->get()
            ->groupBy('type')
            ->map(fn ($group) => $group->first()->getDecryptedData())
            ->all();
    }
}
