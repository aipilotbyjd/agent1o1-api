<?php

namespace App\Services\Workflows\Pages;

use App\Enums\RunStatus;
use App\Models\User;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workflows\WorkflowPageEvent;
use App\Models\Workflows\WorkflowPageSubmission;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * How a page is doing: who opens it, who starts filling it in, how many
 * runs it starts and how they end: overall, by day, and per version while
 * an A/B test runs.
 *
 * A visitor is the random id the page keeps in their browser (sent as
 * `X-Page-Visitor`), a member's user id, or failing both their address and
 * browser, always stored as a keyed hash scoped to the page, so the same
 * person can't be followed from one page to another.
 *
 * @phpstan-type Counts array{views: int, starts: int, submissions: int, completed: int, failed: int, start_rate: ?float, conversion_rate: ?float, completion_rate: ?float, avg_duration_seconds: ?float}
 * @phpstan-type Filters array{status?: ?string, variant?: ?string, include_tests?: bool, from?: ?string, to?: ?string}
 */
class PageInsights
{
    public const string VISITOR_HEADER = 'X-Page-Visitor';

    public const array STATUSES = [
        'completed' => [RunStatus::Completed],
        'failed' => [RunStatus::Failed, RunStatus::Cancelled],
        'running' => [RunStatus::Pending, RunStatus::Running],
        'waiting' => [RunStatus::AwaitingApproval, RunStatus::AwaitingCallback],
    ];

    public function visitor(WorkflowPage $page, Request $request, ?User $user = null): string
    {
        $sent = (string) $request->header(self::VISITOR_HEADER);
        $id = $user?->id
            ?? (preg_match('/^[A-Za-z0-9-]{8,64}$/', $sent) === 1 ? $sent : $request->ip().'|'.$request->userAgent());

        return hash_hmac('sha256', "{$page->id}|{$id}", (string) config('app.key'));
    }

    /**
     * The version a visitor gets. The same visitor always lands in the same
     * bucket, so they see one version for as long as the test runs.
     */
    public function variantFor(WorkflowPage $page, string $visitor): string
    {
        if (! $page->isTestingVariant()) {
            return 'a';
        }

        return hexdec(substr($visitor, 0, 7)) % 100 < $page->variant_split ? 'b' : 'a';
    }

    public function record(WorkflowPage $page, string $type, string $variant, string $visitor): void
    {
        $now = now();

        WorkflowPageEvent::query()->insertOrIgnore([
            'workflow_page_id' => $page->id,
            'variant' => $variant,
            'type' => $type,
            'visitor_hash' => $visitor,
            'day' => $now->toDateString(),
            'created_at' => $now,
        ]);
    }

    /**
     * @return array{range: array{from: string, to: string, days: int}, totals: Counts, daily: array<int, array<string, mixed>>, variants: array<int, array<string, mixed>>}
     */
    public function summary(WorkflowPage $page, int $days, bool $includeTests): array
    {
        $from = now()->startOfDay()->subDays($days - 1);
        $dates = collect(CarbonPeriod::create($from, now()->startOfDay()))->map(fn (Carbon $day): string => $day->toDateString());

        /** @var array<string, array<string, array<string, mixed>>> $tally day => variant => counts */
        $tally = [];

        $page->events()
            ->where('day', '>=', $from->toDateString())
            ->selectRaw('day, variant, type, count(*) as total')
            ->groupBy('day', 'variant', 'type')
            ->get()
            ->each(function (WorkflowPageEvent $row) use (&$tally): void {
                $key = $row->type === WorkflowPageEvent::Start ? 'starts' : 'views';
                $tally[$row->day->toDateString()][$row->variant][$key] = (int) $row->getAttribute('total');
            });

        $this->submissions($page, ['include_tests' => $includeTests, 'from' => $from->toDateString()])
            ->with('run:id,status,started_at,finished_at')
            ->lazy(500)
            ->each(function (WorkflowPageSubmission $submission) use (&$tally): void {
                $cell = &$tally[$submission->created_at->toDateString()][$submission->variant];
                $cell['submissions'] = ($cell['submissions'] ?? 0) + 1;
                $status = $submission->run?->status;

                if ($status === RunStatus::Completed) {
                    $cell['completed'] = ($cell['completed'] ?? 0) + 1;

                    if ($submission->run->started_at && $submission->run->finished_at) {
                        $cell['durations'][] = $submission->run->started_at->diffInSeconds($submission->run->finished_at);
                    }
                } elseif ($status === null || in_array($status, self::STATUSES['failed'], true)) {
                    $cell['failed'] = ($cell['failed'] ?? 0) + 1;
                }
            });

        $daily = $dates->map(function (string $date) use ($tally): array {
            $cells = $tally[$date] ?? [];

            return ['date' => $date, ...array_diff_key(self::counts(array_values($cells)), array_flip(['avg_duration_seconds']))];
        })->values()->all();

        $byVariant = ['a' => [], 'b' => []];

        foreach ($tally as $variants) {
            foreach ($variants as $variant => $cell) {
                $byVariant[$variant][] = $cell;
            }
        }

        $showVariants = $page->isTestingVariant() || $byVariant['b'] !== [];

        return [
            'range' => ['from' => $from->toDateString(), 'to' => now()->toDateString(), 'days' => $days],
            'totals' => self::counts(array_merge(...array_values($byVariant))),
            'daily' => $daily,
            'variants' => $showVariants
                ? array_map(fn (string $variant): array => ['variant' => $variant, ...self::counts($byVariant[$variant])], ['a', 'b'])
                : [],
        ];
    }

    /**
     * A page's submissions narrowed by the table's filters, newest first.
     *
     * @param  Filters  $filters
     * @return HasMany<WorkflowPageSubmission, WorkflowPage>
     */
    public function submissions(WorkflowPage $page, array $filters): HasMany
    {
        $statuses = self::STATUSES[$filters['status'] ?? ''] ?? null;

        return $page->submissions()
            ->when(! ($filters['include_tests'] ?? true), fn (Builder $query) => $query->where('is_test', false))
            ->when(in_array($filters['variant'] ?? null, ['a', 'b'], true), fn (Builder $query) => $query->where('variant', $filters['variant']))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($statuses !== null, function (Builder $query) use ($filters, $statuses): void {
                $query->where(function (Builder $query) use ($filters, $statuses): void {
                    $query->whereHas('run', fn (Builder $run) => $run->whereIn('status', $statuses));

                    // A submission whose run is gone counts as failed.
                    if ($filters['status'] === 'failed') {
                        $query->orWhereNull('run_id');
                    }
                });
            })
            ->latest();
    }

    /**
     * Totals and rates over a set of tallies.
     *
     * @param  array<int, array<string, mixed>>  $cells
     * @return Counts
     */
    private static function counts(array $cells): array
    {
        $sum = fn (string $key): int => (int) array_sum(array_column($cells, $key));
        $durations = array_merge(...array_map(fn (array $cell): array => $cell['durations'] ?? [], $cells));
        [$views, $starts, $submissions, $completed] = [$sum('views'), $sum('starts'), $sum('submissions'), $sum('completed')];

        return [
            'views' => $views,
            'starts' => $starts,
            'submissions' => $submissions,
            'completed' => $completed,
            'failed' => $sum('failed'),
            'start_rate' => $views > 0 ? round($starts / $views, 4) : null,
            'conversion_rate' => $views > 0 ? round($submissions / $views, 4) : null,
            'completion_rate' => $submissions > 0 ? round($completed / $submissions, 4) : null,
            'avg_duration_seconds' => $durations === [] ? null : round(array_sum($durations) / count($durations), 1),
        ];
    }
}
