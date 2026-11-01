<?php

namespace App\Services\Assistant\Runtime;

use App\Ai\Assistant\Tools\AssistantTool;
use App\Models\Assistant\AssistantSession;
use App\Services\Assistant\AssistantInstructions;
use App\Services\Assistant\Tools\ToolCatalog;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Messages\Message;

/**
 * How full a conversation's context window is, and with what: an estimate
 * (characters ÷ chars_per_token), close enough to show the owner when the
 * conversation is about to be summarized.
 */
class ContextMeter
{
    public function __construct(
        private readonly AssistantInstructions $instructions,
        private readonly ToolCatalog $catalog,
        private readonly AssistantModel $model,
        private readonly ContextCompactor $compactor,
    ) {}

    /**
     * @return array{window_tokens: int, used_tokens: int, percent: int, compacted_messages: int, parts: array<string, int>}
     */
    public function measure(AssistantSession $session): array
    {
        $assistant = $session->assistant;
        $recap = (string) $this->instructions->recapFor($session);
        $instructions = mb_strlen($this->instructions->for($assistant, $session)) - mb_strlen($recap);

        $tools = collect($this->catalog->available($assistant))
            ->sum(fn (AssistantTool $tool): int => mb_strlen($tool->name().$tool->description().json_encode(
                collect($tool->schema(new JsonSchemaTypeFactory))->map->toArray()->all(),
            )));

        $conversation = collect((new ConversationHistory($session))->messages())
            ->sum(fn (Message $message): int => mb_strlen((string) $message->content));

        $parts = [
            'instructions' => $this->compactor->estimateTokens(max(0, $instructions)),
            'tools' => $this->compactor->estimateTokens($tools),
            'summary' => $this->compactor->estimateTokens(mb_strlen($recap)),
            'conversation' => $this->compactor->estimateTokens($conversation),
        ];

        $window = $this->model->windowTokens($assistant);
        $used = array_sum($parts);

        return [
            'window_tokens' => $window,
            'used_tokens' => $used,
            'percent' => (int) min(100, round($used / max(1, $window) * 100)),
            'compacted_messages' => $session->messages()->whereNotNull('compacted_into_id')->count(),
            'parts' => $parts,
        ];
    }
}
