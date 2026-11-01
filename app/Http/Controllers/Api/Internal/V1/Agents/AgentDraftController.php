<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Actions\Billing\DeductCreditsAction;
use App\Ai\Agents\AgentDraftAgent;
use App\Ai\ResponseUsage;
use App\Ai\Tools\SubmitAgentDraftTool;
use App\Ai\ToolSubmission;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\DraftAgentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Ai\ModelCatalog;
use App\Models\Workspaces\Workspace;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Billing\CreditGate;
use App\Services\Billing\CreditMeter;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Drafts a new agent's name, description, instructions and look from a
 * plain-language description, using the model the user picked for it. Nothing
 * is saved; the builder creates the agent from the draft.
 */
class AgentDraftController extends Controller
{
    public function __invoke(
        DraftAgentRequest $request,
        Workspace $workspace,
        ModelCatalogResolver $modelCatalog,
        ByokProviderRegistrar $byok,
        CreditGate $creditGate,
        CreditMeter $meter,
        DeductCreditsAction $deductCredits,
    ) {
        $this->requirePermission(Permission::AgentManage);
        $creditGate->assertCanStartRun($workspace);

        $catalog = ModelCatalog::query()->findOrFail($request->validated('model_catalog_id'));

        [$provider] = $byok->apply($modelCatalog->providerChain($catalog->slug), null, $workspace->id, $request->user()->id);

        $startedAt = now();

        $response = (new AgentDraftAgent)->prompt(
            $request->validated('prompt'),
            provider: $provider,
        );

        $draft = Arr::only(
            ToolSubmission::arguments($response, SubmitAgentDraftTool::NAME),
            ['name', 'description', 'instructions', 'icon', 'color'],
        );

        $deductCredits->execute(
            $workspace,
            CreditTransactionType::AgentDraft,
            (string) Str::orderedUuid(),
            $meter->costForAgentDraft(ResponseUsage::from($response, $startedAt)),
            'Agent draft',
            allowOverdraft: true,
        );

        $draft['icon'] = in_array($draft['icon'] ?? null, Agent::ICONS, true) ? $draft['icon'] : Agent::ICONS[0];
        $draft['color'] = in_array($draft['color'] ?? null, Agent::COLORS, true) ? $draft['color'] : Agent::COLORS[0];

        return ApiResponse::success(['draft' => $draft]);
    }
}
