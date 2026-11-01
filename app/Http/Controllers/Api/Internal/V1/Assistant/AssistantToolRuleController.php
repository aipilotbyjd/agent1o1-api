<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Ai\Assistant\Tools\AssistantTool;
use App\Enums\Assistant\AssistantToolRule;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Assistant\Concerns\ResolvesOwnAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Assistant\UpdateAssistantToolRulesRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Assistant\Assistant;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\Tools\ToolCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The owner's "always allow / ask / never" choice per tool. Destructive
 * tools can't be set to always allow.
 */
class AssistantToolRuleController extends Controller
{
    use ResolvesOwnAssistant;

    public function __construct(private ToolCatalog $catalog) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        return ApiResponse::success(['tools' => $this->describe($this->ownAssistant($request, $workspace))]);
    }

    public function update(UpdateAssistantToolRulesRequest $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $assistant = $this->ownAssistant($request, $workspace);
        $tools = collect($this->catalog->available($assistant))->keyBy(fn (AssistantTool $tool): string => $tool->name());

        foreach ($request->validated('rules') as $index => $entry) {
            $tool = $tools->get($entry['tool']);

            abort_if($tool === null, 422, "Unknown tool [{$entry['tool']}].");

            $rule = AssistantToolRule::tryFrom((string) ($entry['rule'] ?? ''));

            abort_if(
                $rule === AssistantToolRule::Allow && ! $tool->effect()->canBeAutoAllowed(),
                422,
                "{$tool->name()} always asks first and can't be set to always allow.",
            );

            $rule === null
                ? $assistant->toolRules()->where('tool', $tool->name())->delete()
                : $assistant->toolRules()->updateOrCreate(['tool' => $tool->name()], ['rule' => $rule]);
        }

        return ApiResponse::success(['tools' => $this->describe($assistant)], 'Tool rules updated.');
    }

    /**
     * @return list<array{name: string, effect: string, default_rule: string, rule: string|null, can_auto_allow: bool}>
     */
    private function describe(Assistant $assistant): array
    {
        $rules = $assistant->toolRules()->pluck('rule', 'tool');

        return collect($this->catalog->available($assistant))
            ->map(fn (AssistantTool $tool): array => [
                'name' => $tool->name(),
                'effect' => $tool->effect()->value,
                'default_rule' => $tool->effect()->defaultRule()->value,
                'rule' => $rules->get($tool->name())?->value,
                'can_auto_allow' => $tool->effect()->canBeAutoAllowed(),
            ])
            ->values()
            ->all();
    }
}
