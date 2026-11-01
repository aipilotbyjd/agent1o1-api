<?php

namespace App\Ai\Tools;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentMemory;
use App\Services\Agents\SkillInjector;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Looks up remembered facts that didn't fit in the prompt. `SkillInjector`
 * only injects the `MAX_INJECTED_MEMORIES` most recently updated ones;
 * `ToolRegistry` attaches this tool only when there are more than that.
 */
class RecallMemoriesTool implements Tool
{
    public const NAME = 'recall_memories';

    private const int MAX_RESULTS = 20;

    public function __construct(
        private readonly Agent $agent,
        private readonly ?string $userId = null,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Searches the facts you remembered that are not listed in your instructions. '
            .'Use it when an older fact about the user or their work might matter to the request. '
            .'Pass a word or two to look for, e.g. "billing" or "manager".';
    }

    public function handle(Request $request): Stringable|string
    {
        $terms = array_filter(preg_split('/\s+/', mb_strtolower(trim((string) $request['query']))) ?: [], filled(...));

        $matches = $this->agent->memoriesVisibleTo($this->userId)
            ->where(function ($query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->orWhereRaw('lower(key) like ?', ["%{$term}%"])
                        ->orWhereRaw('lower(value) like ?', ["%{$term}%"]);
                }
            })
            ->latest('updated_at')
            ->limit(self::MAX_RESULTS)
            ->get();

        if ($matches->isEmpty()) {
            return 'No remembered facts match that.';
        }

        return $matches->map(fn (AgentMemory $memory): string => "- {$memory->key}: {$memory->value}")->implode("\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
        ];
    }

    public static function isNeededFor(Agent $agent, ?string $userId): bool
    {
        return $agent->memoriesVisibleTo($userId)->count() > SkillInjector::MAX_INJECTED_MEMORIES;
    }
}
