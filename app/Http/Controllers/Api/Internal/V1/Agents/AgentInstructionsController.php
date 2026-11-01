<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Actions\Billing\DeductCreditsAction;
use App\Ai\Agents\AgentInstructionsAgent;
use App\Ai\ResponseUsage;
use App\Ai\Tools\SubmitAgentInstructionsTool;
use App\Ai\ToolSubmission;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\ImproveAgentInstructionsRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\ToolRegistry;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Billing\CreditGate;
use App\Services\Billing\CreditMeter;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Tools\ToolNameResolver;

/**
 * Rewrites an agent's instructions with its own model, from its name,
 * purpose, current instructions and the tools it can actually call, plus an
 * optional change the user asks for. `instructions`, when sent, stands in
 * for the saved ones so unsaved edits in the builder are what gets rewritten.
 * Nothing is saved; the builder puts the result in the instructions editor
 * for the user to review.
 */
class AgentInstructionsController extends Controller
{
    public function __invoke(
        ImproveAgentInstructionsRequest $request,
        Workspace $workspace,
        Agent $agent,
        ToolRegistry $tools,
        ModelCatalogResolver $modelCatalog,
        ByokProviderRegistrar $byok,
        CreditGate $creditGate,
        CreditMeter $meter,
        DeductCreditsAction $deductCredits,
    ) {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        $creditGate->assertCanStartRun($workspace);

        [$provider, $model] = $byok->apply(...$modelCatalog->forAgent($agent), workspaceId: $workspace->id, userId: $request->user()->id);

        $startedAt = now();

        $response = (new AgentInstructionsAgent)->prompt(
            $this->prompt(
                $agent,
                $tools,
                $request->has('instructions') ? $request->validated('instructions') : $agent->instructions,
                $request->validated('request'),
            ),
            provider: $provider,
            model: $model,
        );

        $instructions = ToolSubmission::arguments($response, SubmitAgentInstructionsTool::NAME)['instructions'] ?? '';

        $deductCredits->execute(
            $workspace,
            CreditTransactionType::AgentDraft,
            (string) Str::orderedUuid(),
            $meter->costForAgentDraft(ResponseUsage::from($response, $startedAt)),
            'Agent instructions',
            allowOverdraft: true,
        );

        return ApiResponse::success(['instructions' => trim((string) $instructions)]);
    }

    private function prompt(Agent $agent, ToolRegistry $tools, ?string $current, ?string $change): string
    {
        // The tool list only; nothing here runs, so the run is never saved.
        $toolLines = collect($tools->toolsFor($agent, new Run(['workspace_id' => $agent->workspace_id])))
            ->map(fn ($tool): string => match (true) {
                $tool instanceof WebSearch => '- web_search: Searches the web.',
                $tool instanceof WebFetch => '- web_fetch: Reads a web page by URL.',
                $tool instanceof Tool => '- '.ToolNameResolver::resolve($tool).': '.$tool->description(),
            })
            ->implode("\n");

        return implode("\n\n", array_filter([
            "Name: {$agent->name}",
            filled($agent->description) ? "Purpose: {$agent->description}" : null,
            "Current instructions:\n".(filled($current) ? $current : '(none)'),
            "Tools it can call:\n{$toolLines}",
            filled($change) ? "Requested change: {$change}" : null,
        ]));
    }
}
