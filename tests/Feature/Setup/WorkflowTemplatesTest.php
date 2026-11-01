<?php

use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\DryRunner;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    $this->syncSetup('workflow-templates');
});

it('publishes every shipped workflow template as a real workflow', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    foreach (WorkflowTemplate::query()->whereNull('workspace_id')->get() as $template) {
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $workflow->replaceGraph($template->graph);

        expect(fn () => $workflow->publishVersion(publisher: $owner))->not->toThrow(Throwable::class, "[{$template->slug}] would not publish");
    }
});

it('dry-runs every shipped workflow template without an unresolved reference', function () {
    foreach (WorkflowTemplate::query()->whereNull('workspace_id')->get() as $template) {
        $result = app(DryRunner::class)->run($template->graph);

        expect($result['ok'])->toBeTrue("[{$template->slug}] failed its dry run")
            ->and($result['warnings'])->toBe([], "[{$template->slug}] refers to something that does not exist");
    }
});

it('builds the routing workflows on the AI nodes rather than on prompts that must be parsed', function (string $slug, string $nodeType) {
    $types = collect(WorkflowTemplate::query()->whereNull('workspace_id')->where('slug', $slug)->firstOrFail()->graph['nodes'])->pluck('type');

    expect($types)->toContain($nodeType);
})->with([
    ['lead-intake-and-scoring', 'ai_classify'],
    ['webhook-lead-capture-and-routing', 'ai_extract'],
    ['failed-payment-recovery', 'ai_extract'],
    ['shopify-order-risk-check', 'ai_classify'],
    ['refund-request-with-approval', 'ai_classify'],
    ['support-email-triage-and-reply', 'ai_classify'],
    ['ticket-to-github-issue', 'ai_extract'],
    ['resume-screening-with-shortlist', 'ai_score'],
    ['invoice-capture-and-approval', 'ai_extract'],
    ['it-request-to-jira', 'ai_classify'],
    ['meeting-notes-to-actions', 'ai_summarize'],
    ['ticket-classify-and-route', 'ai_classify'],
    ['review-sentiment-response', 'ai_sentiment'],
    ['multilingual-support-reply', 'ai_translate'],
    ['inbound-email-to-crm-contact', 'ai_extract'],
]);

it('only follows branches the AI node can actually produce', function () {
    foreach (WorkflowTemplate::query()->whereNull('workspace_id')->get() as $template) {
        $nodes = collect($template->graph['nodes'])->keyBy('key');

        foreach ($template->graph['edges'] as $edge) {
            $from = $nodes[$edge['from']];

            if ($edge['condition'] === null || ! in_array($from['type'], ['ai_classify', 'ai_sentiment', 'ai_score'], true)) {
                continue;
            }

            $allowed = match ($from['type']) {
                'ai_classify' => [...$from['config']['categories'], ...(($from['config']['allow_other'] ?? false) ? ['other'] : [])],
                'ai_sentiment' => ['positive', 'neutral', 'negative', 'mixed'],
                'ai_score' => ['strong', 'moderate', 'weak'],
            };

            expect(in_array($edge['condition'], $allowed, true))->toBeTrue("[{$template->slug}] follows “{$edge['condition']}” out of {$edge['from']}, which never returns it");
        }
    }
});
