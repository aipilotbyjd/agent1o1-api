<?php

namespace App\Http\Controllers\Api\Internal\V1\Ai;

use App\Actions\Billing\DeductCreditsAction;
use App\Ai\Agents\AdHocPromptAgent;
use App\Ai\ResponseUsage;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Ai\TryModelRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Ai\ModelCatalog;
use App\Models\Workspaces\Workspace;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Ai\WorkspaceModels;
use App\Services\Billing\CreditGate;
use App\Services\Billing\CreditMeter;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\FailoverableException;
use RuntimeException;
use Throwable;

/**
 * "Try this model" in the model picker: one short prompt, answered by the
 * model exactly as an agent on it would be (the workspace's own key where
 * it has one) and charged like the chat turn it is. Reports how long it
 * took, the tokens and the credits, so members can compare models on
 * their own question before switching.
 */
class ModelTrialController extends Controller
{
    private const string INSTRUCTIONS = 'You are being tried out in a model picker. Answer the user\'s message helpfully and concisely.';

    public function __invoke(
        TryModelRequest $request,
        Workspace $workspace,
        string $slug,
        ModelCatalogResolver $resolver,
        ByokProviderRegistrar $byok,
        WorkspaceModels $models,
        CreditGate $creditGate,
        CreditMeter $meter,
        DeductCreditsAction $deductCredits,
    ) {
        $this->requirePermission(Permission::AgentChat);

        $catalog = ModelCatalog::query()->where('slug', $slug)->where('is_active', true)->where('is_internal', false)->firstOrFail();
        abort_unless($models->allows($workspace->id, $catalog->id), 422, 'This model isn\'t allowed in this workspace.');
        $creditGate->assertCanStartRun($workspace);

        try {
            $chain = $resolver->providerChain($catalog->slug);
        } catch (RuntimeException) {
            return ApiResponse::error("{$catalog->display_name} isn't available right now.", 422);
        }

        [$provider] = $byok->apply($chain, null, $workspace->id, $request->user()->id);

        $startedAt = now();

        try {
            $response = (new AdHocPromptAgent(self::INSTRUCTIONS))->prompt($request->validated('prompt'), provider: $provider);
        } catch (FailoverableException) {
            return ApiResponse::error("{$catalog->display_name} is busy or rate-limited right now. Try again in a moment.", 503);
        } catch (Throwable $exception) {
            $rejected = $byok->rejectedKey($exception, $provider);

            if ($rejected !== null) {
                $label = config("byok.providers.{$rejected->execution_provider}.label", $rejected->execution_provider);

                return ApiResponse::error("{$label} rejected your key: it may have expired or been revoked. Replace it in Settings → AI Providers.", 422);
            }

            report($exception);

            return ApiResponse::error("{$catalog->display_name} didn't answer. Try again, or pick another model.", 502);
        }

        $usage = ResponseUsage::from($response, $startedAt);
        $credits = $meter->costForModelTrial($usage);

        $deductCredits->execute(
            $workspace,
            CreditTransactionType::ModelTrial,
            (string) Str::orderedUuid(),
            $credits,
            "Model trial ({$catalog->display_name})",
            allowOverdraft: true,
        );

        return ApiResponse::success(['trial' => [
            'text' => $response->text,
            'duration_ms' => (int) $startedAt->diffInMilliseconds(now()),
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'credits' => $credits,
            'served_by_own_key' => ByokProviderRegistrar::isByok($usage['provider'] ?? null),
        ]]);
    }
}
