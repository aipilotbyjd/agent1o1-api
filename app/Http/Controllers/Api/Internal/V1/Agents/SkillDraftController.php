<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Actions\Billing\DeductCreditsAction;
use App\Ai\Agents\SkillDraftAgent;
use App\Ai\ResponseUsage;
use App\Ai\Tools\SubmitSkillDraftTool;
use App\Ai\ToolSubmission;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\DraftSkillRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Skill;
use App\Models\Agents\SkillScript;
use App\Models\Ai\ModelCatalog;
use App\Models\Workspaces\Workspace;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Billing\CreditGate;
use App\Services\Billing\CreditMeter;
use Illuminate\Support\Str;

/**
 * Drafts a new skill (its name, description, instructions, references and
 * scripts) from a plain-language description, using the model the user
 * picked for it. Nothing is saved; the Skills page creates the skill, its
 * references and its scripts from the draft.
 */
class SkillDraftController extends Controller
{
    public function __invoke(
        DraftSkillRequest $request,
        Workspace $workspace,
        ModelCatalogResolver $modelCatalog,
        ByokProviderRegistrar $byok,
        CreditGate $creditGate,
        CreditMeter $meter,
        DeductCreditsAction $deductCredits,
    ) {
        $this->requirePermission(Permission::AgentSkillManage);
        $creditGate->assertCanStartRun($workspace);

        $catalog = ModelCatalog::query()->findOrFail($request->validated('model_catalog_id'));

        [$provider] = $byok->apply($modelCatalog->providerChain($catalog->slug), null, $workspace->id, $request->user()->id);

        $startedAt = now();

        $response = (new SkillDraftAgent)->prompt(
            $request->validated('prompt'),
            provider: $provider,
        );

        $submission = ToolSubmission::arguments($response, SubmitSkillDraftTool::NAME);

        $deductCredits->execute(
            $workspace,
            CreditTransactionType::SkillDraft,
            (string) Str::orderedUuid(),
            $meter->costForSkillDraft(ResponseUsage::from($response, $startedAt)),
            'Skill draft',
            allowOverdraft: true,
        );

        return ApiResponse::success(['draft' => $this->normalize($submission)]);
    }

    /**
     * Shapes the model's submission into fields the skill endpoints accept,
     * dropping references and scripts too incomplete to save.
     *
     * @param  array<string, mixed>  $submission
     * @return array{name: string, description: string, category: string, icon: string, color: string, tags: list<string>, instructions: string, references: list<array{title: string, content: string}>, scripts: list<array{name: string, description: string, language: string, code: string}>}
     */
    private function normalize(array $submission): array
    {
        $text = fn (mixed $value): string => is_string($value) ? trim($value) : '';

        $references = collect(is_array($submission['references'] ?? null) ? $submission['references'] : [])
            ->filter(fn (mixed $reference): bool => is_array($reference))
            ->map(fn (array $reference): array => [
                'title' => Str::limit($text($reference['title'] ?? null), 255, ''),
                'content' => $text($reference['content'] ?? null),
            ])
            ->filter(fn (array $reference): bool => $reference['title'] !== '' && $reference['content'] !== '')
            ->values()
            ->all();

        $scripts = collect(is_array($submission['scripts'] ?? null) ? $submission['scripts'] : [])
            ->filter(fn (mixed $script): bool => is_array($script))
            ->map(fn (array $script): array => [
                'name' => Str::limit($text($script['name'] ?? null), 255, ''),
                'description' => $text($script['description'] ?? null),
                'language' => $text($script['language'] ?? null),
                'code' => $text($script['code'] ?? null),
            ])
            ->filter(fn (array $script): bool => $script['name'] !== '' && $script['code'] !== '' && in_array($script['language'], SkillScript::LANGUAGES, true))
            ->values()
            ->all();

        $tags = collect(is_array($submission['tags'] ?? null) ? $submission['tags'] : [])
            ->map($text)
            ->filter()
            ->unique()
            ->take(5)
            ->values()
            ->all();

        return [
            'name' => Str::limit($text($submission['name'] ?? null), 255, ''),
            'description' => $text($submission['description'] ?? null),
            'category' => in_array($submission['category'] ?? null, Skill::CATEGORIES, true) ? $submission['category'] : Skill::CATEGORIES[0],
            'icon' => in_array($submission['icon'] ?? null, Skill::ICONS, true) ? $submission['icon'] : Skill::ICONS[0],
            'color' => in_array($submission['color'] ?? null, Skill::COLORS, true) ? $submission['color'] : Skill::COLORS[0],
            'tags' => $tags,
            'instructions' => $text($submission['instructions'] ?? null),
            'references' => $references,
            'scripts' => $scripts,
        ];
    }
}
