<?php

namespace App\Services\Workflows\Pages;

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workflows\WorkflowPageSubmission;
use App\Services\Workflows\WorkflowInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Starting a run from a page and reading back what the page may show of it.
 *
 * A submission is validated against the workflow's interface (the same
 * rules the run form uses) so a page can't start a run the workflow would
 * refuse. Its result is filtered down to the outputs the page displays:
 * a visitor never receives other values, step outputs or error details.
 */
class PageSubmissions
{
    public function __construct(
        private readonly WorkflowInterface $interface,
        private readonly StartWorkflowRunAction $startWorkflowRun,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: WorkflowPageSubmission, 1: string} the submission and the secret that reads its result
     */
    public function submit(WorkflowPage $page, array $input, ?User $user, ?string $visitorHash = null, bool $test = false, string $variant = 'a'): array
    {
        $workflow = $page->workflow;

        abort_if($workflow === null || $workflow->trashed(), 404, 'This page is not available.');

        if (! $test) {
            $this->assertUnderDailyLimit($page);
        }

        $validated = Validator::make(
            ['input' => $input],
            ['input' => ['nullable', 'array'], ...$this->interface->rules($workflow)],
            [],
            collect($this->interface->describe($workflow)['fields'])
                ->mapWithKeys(fn (array $field): array => ["input.{$field['key']}" => $field['label'] ?: $field['key']])
                ->all(),
        )->validate();

        $input = $this->interface->applyDefaults($workflow, $validated['input'] ?? []);

        $run = $this->startWorkflowRun->execute(
            $workflow,
            $input,
            // An anonymous visitor runs the workflow on behalf of whoever
            // published the page, the way a trigger runs as its creator.
            $user ?? $page->publisher ?? $page->creator,
            'page',
        );

        $secret = Str::random(40);

        $submission = $page->submissions()->create([
            'workspace_id' => $page->workspace_id,
            'run_id' => $run->id,
            'user_id' => $user?->id,
            'visitor_hash' => $visitorHash,
            'secret_hash' => hash('sha256', $secret),
            'is_test' => $test,
            'variant' => $variant === 'b' ? 'b' : 'a',
            'input' => $input,
        ]);

        return [$submission->setRelation('run', $run), $secret];
    }

    public function secretMatches(WorkflowPageSubmission $submission, ?string $secret): bool
    {
        return $secret !== null && hash_equals($submission->secret_hash, hash('sha256', $secret));
    }

    /**
     * What a page shows of a submission's run. `$content` is the page content
     * the submission was made against: the draft for a test run from the
     * builder, the published page otherwise. Members also get the run's id
     * and its real error, to open it in run history.
     *
     * @param  array{blocks: array<int, array<string, mixed>>, theme: array<string, mixed>}  $content
     * @return array<string, mixed>
     */
    public function result(WorkflowPageSubmission $submission, array $content, bool $forMember): array
    {
        $run = $submission->run;
        $state = $this->state($run);
        $output = $run?->output ?? [];

        $outputs = [];

        if ($run !== null && $run->status === RunStatus::Completed) {
            foreach (PageSchema::shownOutputs($content) as $name) {
                if (array_key_exists($name, $output)) {
                    $outputs[$name] = $output[$name];
                }
            }
        }

        $result = [
            'id' => $submission->id,
            'state' => $state,
            'outputs' => (object) $outputs,
            'progress' => $this->progress($run),
            'error' => $state === 'failed'
                ? ($forMember && filled($run?->error) ? $run->error : 'The workflow could not finish. Try again in a moment.')
                : null,
            'started_at' => $submission->created_at?->toIso8601String(),
            'finished_at' => $run?->finished_at?->toIso8601String(),
        ];

        if ($forMember) {
            $result['run_id'] = $run?->id;
            $result['is_test'] = $submission->is_test;
        }

        return $result;
    }

    /**
     * @return 'running'|'waiting'|'completed'|'failed'
     */
    private function state(?Run $run): string
    {
        return match ($run?->status) {
            RunStatus::Completed => 'completed',
            RunStatus::AwaitingApproval, RunStatus::AwaitingCallback => 'waiting',
            RunStatus::Failed, RunStatus::Cancelled, null => 'failed',
            default => 'running',
        };
    }

    /**
     * How far along the run is, as counts only: a visitor learns how many
     * steps there are, not what they are.
     *
     * @return array{completed: int, total: int}
     */
    private function progress(?Run $run): array
    {
        if ($run === null) {
            return ['completed' => 0, 'total' => 0];
        }

        $total = count($run->workflowVersion?->graph['nodes'] ?? []);
        $completed = $run->nodeRuns()
            ->whereIn('status', [NodeRunStatus::Completed, NodeRunStatus::Skipped])
            ->count();

        return ['completed' => min($completed, $total), 'total' => $total];
    }

    private function assertUnderDailyLimit(WorkflowPage $page): void
    {
        if ($page->max_runs_per_day === null) {
            return;
        }

        $used = $page->submissions()
            ->where('is_test', false)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($used >= $page->max_runs_per_day) {
            throw new TooManyRequestsHttpException(null, 'This page has reached its limit for today. Try again tomorrow.');
        }
    }
}
