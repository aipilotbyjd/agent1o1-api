<?php

namespace App\Jobs\Pages;

use App\Actions\Billing\DeductCreditsAction;
use App\Ai\Agents\PageDesignerAgent;
use App\Ai\ResponseUsage;
use App\Ai\Tools\SubmitPageTool;
use App\Ai\ToolSubmission;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Queue;
use App\Events\Pages\PageActivity;
use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
use App\Models\Workflows\WorkflowPageGeneration;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Billing\CreditMeter;
use App\Services\Workflows\Pages\PageSchema;
use App\Services\Workflows\WorkflowInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Has `PageDesignerAgent` design or rework a page for a generation request,
 * and stores what it made (bound to the workflow's real inputs and outputs)
 * as the generation's proposed content. Nothing on the page changes here;
 * the builder shows the proposal and the member decides.
 *
 * Runs on the workflow-builder assistant's model route, on the workspace's
 * own provider key when the member has one (see `ByokProviderRegistrar`).
 */
class GeneratePageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const string FAILURE_MESSAGE = 'The AI couldn’t design this page. Try again, or describe what you want differently.';

    /** How much of the current page the model sees, in characters of JSON. */
    private const int CURRENT_PAGE_LIMIT = 24000;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public string $generationId)
    {
        $this->onQueue(Queue::WorkflowBuilder->value);
    }

    public function handle(
        PageSchema $schema,
        WorkflowInterface $interface,
        ModelCatalogResolver $modelCatalog,
        ByokProviderRegistrar $byok,
        CreditMeter $meter,
        DeductCreditsAction $deductCredits,
    ): void {
        $generation = WorkflowPageGeneration::query()->with('page.workflow', 'page.workspace')->find($this->generationId);

        if ($generation === null || $generation->isFinished()) {
            return;
        }

        $page = $generation->page;
        $workflow = $page->workflow;

        if ($workflow === null || $workflow->trashed()) {
            $this->markFailed($generation, 'The workflow this page runs is in the trash.');

            return;
        }

        $generation->update(['status' => WorkflowPageGeneration::Running]);
        $current = $page->contentFor($generation->variant, published: false) ?? $page->content;
        $startedAt = now();

        try {
            $response = (new PageDesignerAgent)->prompt(
                $this->prompt($generation, $interface->describe($workflow)['fields'], $interface->outputs($workflow), $current),
                provider: $this->provider($modelCatalog, $byok, $generation),
            );

            $content = $schema->bindToWorkflow(ToolSubmission::arguments($response, SubmitPageTool::NAME), $workflow);
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed($generation, self::FAILURE_MESSAGE);

            return;
        }

        if ($generation->keep_theme) {
            $content['theme'] = $current['theme'];
        }

        $generation->update(['status' => WorkflowPageGeneration::Completed, 'content' => $content]);

        $deductCredits->execute(
            $page->workspace,
            CreditTransactionType::PageGeneration,
            $generation->id,
            $meter->costForPageGeneration(ResponseUsage::from($response, $startedAt)),
            'Page generation',
            allowOverdraft: true,
        );

        PageActivity::dispatch($page, 'generation', ['id' => $generation->id, 'status' => WorkflowPageGeneration::Completed]);
    }

    /**
     * Reached when the worker itself gave up on the job (timeout, crash).
     */
    public function failed(?Throwable $exception): void
    {
        $generation = WorkflowPageGeneration::query()->with('page')->find($this->generationId);

        if ($generation !== null && ! $generation->isFinished()) {
            $this->markFailed($generation, self::FAILURE_MESSAGE);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<int, array{name: string}>  $outputs
     * @param  array<string, mixed>  $current
     */
    private function prompt(WorkflowPageGeneration $generation, array $fields, array $outputs, array $current): string
    {
        $page = $generation->page;
        $workflow = $page->workflow;
        $inputs = array_map(fn (array $field): array => array_filter([
            'key' => $field['key'],
            'label' => $field['label'] ?? null,
            'type' => $field['type'],
            'required' => $field['required'],
            'choices' => array_column($field['options'] ?? [], 'value') ?: null,
            'help' => $field['help'] ?? null,
        ], fn (mixed $value): bool => $value !== null), $fields);

        $json = fn (mixed $value): string => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return implode("\n\n", [
            "Request:\n{$generation->prompt}",
            "Page name: {$page->name}",
            'Workflow: '.$workflow->name.(filled($workflow->description) ? " - {$workflow->description}" : ''),
            "Workflow inputs (one field block each):\n".$json($inputs),
            "Workflow outputs (show with output blocks):\n".$json(array_column($outputs, 'name')),
            "Current page:\n".Str::limit($json($current), self::CURRENT_PAGE_LIMIT, ' …(cut)'),
        ]);
    }

    /**
     * @return array<string, string>|null
     */
    private function provider(ModelCatalogResolver $modelCatalog, ByokProviderRegistrar $byok, WorkflowPageGeneration $generation): ?array
    {
        try {
            $chain = $modelCatalog->providerChain(ProcessWorkflowBuilderMessageJob::MODEL_CATALOG_SLUG);
        } catch (RuntimeException) {
            return null;
        }

        [$provider] = $byok->apply($chain, null, $generation->page->workspace_id, $generation->user_id);

        return $provider;
    }

    private function markFailed(WorkflowPageGeneration $generation, string $error): void
    {
        $generation->update(['status' => WorkflowPageGeneration::Failed, 'error' => $error]);

        PageActivity::dispatch($generation->page, 'generation', ['id' => $generation->id, 'status' => WorkflowPageGeneration::Failed]);
    }
}
