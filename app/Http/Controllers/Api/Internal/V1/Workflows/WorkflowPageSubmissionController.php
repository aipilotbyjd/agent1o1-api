<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\SubmitWorkflowPageRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowPageSubmissionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workflows\WorkflowPageSubmission;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\Pages\PageInsights;
use App\Services\Workflows\Pages\PageSchema;
use App\Services\Workflows\Pages\PageSubmissions;
use App\Services\Workflows\WorkflowInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Members using a page from inside the app, and its run history. A test run
 * (`test: true`) comes from the builder's preview: it may use an unpublished
 * page and doesn't count toward the page's daily limit, so it needs
 * `page.manage`.
 */
class WorkflowPageSubmissionController extends Controller
{
    private const int PER_PAGE = 50;

    public function __construct(
        private readonly PageSubmissions $submissions,
        private readonly PageInsights $insights,
    ) {}

    /**
     * The page's submissions, newest first, a page at a time: narrowed by
     * status, version, date and whether builder test runs are included.
     */
    public function index(Request $request, Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageView);
        $this->ensureBelongsToWorkspace($workspace, $page);

        $filters = $this->filters($request);

        $history = $this->insights->submissions($page, $filters)
            ->with(['user:id,name', 'run:id,status,started_at,finished_at,input,output'])
            ->paginate(min(100, max(1, $request->integer('per_page', self::PER_PAGE))));

        $history->getCollection()->each->setRelation('page', $page);

        return ApiResponse::success([
            'submissions' => WorkflowPageSubmissionResource::collection($history->getCollection()),
            'meta' => [
                'total' => $history->total(),
                'page' => $history->currentPage(),
                'per_page' => $history->perPage(),
                'last_page' => $history->lastPage(),
            ],
        ]);
    }

    /**
     * The same submissions as `index()`, every one of them, as a CSV file:
     * when, how it ended, who, the version, the inputs and the outputs the
     * page shows.
     */
    public function export(Request $request, Workspace $workspace, WorkflowPage $page, WorkflowInterface $interface): StreamedResponse
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        $fields = $page->workflow === null ? [] : $interface->describe($page->workflow)['fields'];
        $outputs = collect([$page->published_content, $page->published_variant_content, $page->content])
            ->filter()
            ->flatMap(fn (array $content): array => PageSchema::shownOutputs($content))
            ->unique()
            ->values()
            ->all();
        $query = $this->insights->submissions($page, $this->filters($request))
            ->with(['user:id,name', 'run:id,status,started_at,finished_at,input,output']);
        $name = Str::slug($page->name) ?: 'page';

        return response()->streamDownload(function () use ($query, $fields, $outputs): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Submitted at', 'Status', 'Person', 'Version', 'Test run', 'Run time (s)',
                ...array_map(fn (array $field): string => (string) ($field['label'] ?: $field['key']), $fields),
                ...$outputs,
            ]);

            $query->lazy(500)->each(function (WorkflowPageSubmission $submission) use ($out, $fields, $outputs): void {
                $run = $submission->run;
                $input = $submission->input ?? $run?->input ?? [];
                $output = $run?->output ?? [];

                fputcsv($out, [
                    $submission->created_at?->toIso8601String(),
                    $run?->status->value ?? 'failed',
                    $submission->user?->name ?? 'Visitor',
                    strtoupper($submission->variant),
                    $submission->is_test ? 'yes' : 'no',
                    $run?->started_at && $run->finished_at ? $run->started_at->diffInSeconds($run->finished_at) : '',
                    ...array_map(fn (array $field): string => self::cell($input[$field['key']] ?? null), $fields),
                    ...array_map(fn (string $name): string => self::cell($output[$name] ?? null), $outputs),
                ]);
            });

            fclose($out);
        }, "{$name}-submissions-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(SubmitWorkflowPageRequest $request, Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::RunTrigger);
        $this->ensureBelongsToWorkspace($workspace, $page);

        $test = $request->boolean('test');

        if ($test) {
            $this->requirePermission(Permission::PageManage);
        } else {
            abort_if(! $page->isPublished(), 409, 'Publish this page before using it.');
            abort_if(! $page->is_enabled, 409, 'This page is turned off.');
        }

        $variant = $test
            ? ($request->validated('variant') ?? 'a')
            : $this->insights->variantFor($page, $this->insights->visitor($page, $request, $request->user()));

        [$submission] = $this->submissions->submit($page, $request->validated('input') ?? [], $request->user(), test: $test, variant: $variant);

        return ApiResponse::success([
            'submission' => $this->submissions->result($submission, $this->contentFor($page, $submission), forMember: true),
        ], 'Run started.', 202);
    }

    public function show(Workspace $workspace, WorkflowPage $page, WorkflowPageSubmission $submission)
    {
        $this->requirePermission(Permission::PageView);
        $this->ensureBelongsToWorkspace($workspace, $page);
        abort_if($submission->workflow_page_id !== $page->id, 404);

        return ApiResponse::success([
            'submission' => $this->submissions->result($submission->load('run'), $this->contentFor($page, $submission), forMember: true),
        ]);
    }

    /**
     * @return array{blocks: array<int, array<string, mixed>>, theme: array<string, mixed>}
     */
    private function contentFor(WorkflowPage $page, WorkflowPageSubmission $submission): array
    {
        return $page->contentFor($submission->variant, published: ! $submission->is_test)
            ?? $page->published_content
            ?? $page->content;
    }

    /**
     * @return array{status: ?string, variant: ?string, include_tests: bool, from: ?string, to: ?string}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', array_keys(PageInsights::STATUSES))],
            'variant' => ['nullable', 'in:a,b'],
            'include_tests' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return [
            'status' => $validated['status'] ?? null,
            'variant' => $validated['variant'] ?? null,
            'include_tests' => $request->boolean('include_tests', true),
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }

    /**
     * A value as one CSV cell: text as it is, anything structured as JSON.
     */
    private static function cell(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'yes' : 'no',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        };
    }
}
