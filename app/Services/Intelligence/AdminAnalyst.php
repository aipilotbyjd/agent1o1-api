<?php

namespace App\Services\Intelligence;

use App\Ai\Agents\Intelligence\AdminAnalystAgent;
use App\Ai\Observability\AiCallContext;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;

/**
 * Puts an operator's question to the admin analyst and returns its answer
 * with the tools it consulted. The question is audited; the calls it makes
 * are on the platform's own account, recorded in the call ledger under the
 * `admin_analyst` feature and never billed to a customer.
 */
class AdminAnalyst
{
    public function __construct(
        private readonly ModelCatalogResolver $catalog,
        private readonly AiCallContext $context,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    /**
     * @return array{answer: string, tools: list<string>, model: string|null}
     */
    public function ask(SuperAdmin $actor, string $question): array
    {
        $slug = config('intelligence.analyst.model');
        $provider = is_string($slug) && $slug !== '' ? $this->catalog->providerChain($slug) : null;

        $response = $this->context->run(['feature' => 'admin_analyst', 'user_id' => (string) $actor->getKey()], fn () => (new AdminAnalystAgent)->prompt($question, provider: $provider));

        $tools = collect($response->steps ?? [])
            ->flatMap(fn ($step) => collect($step->toolCalls ?? [])->pluck('name'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->audit->record($actor, 'intelligence.analyst.asked', null, null, ['question' => mb_substr($question, 0, 500), 'tools' => $tools], null);

        return [
            'answer' => (string) $response->text,
            'tools' => $tools,
            'model' => $response->meta->model ?? null,
        ];
    }
}
