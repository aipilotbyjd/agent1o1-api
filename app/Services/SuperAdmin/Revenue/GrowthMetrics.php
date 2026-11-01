<?php

namespace App\Services\SuperAdmin\Revenue;

use App\Enums\RunStatus;
use App\Models\Billing\UsagePeriod;
use App\Models\Referrals\Referral;
use App\Models\Runs\Run;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Product growth numbers: how signups move through the funnel, whether they
 * stay, and where they come from. A user's activity is the activity of the
 * workspaces they own. Everything is in UTC.
 */
class GrowthMetrics
{
    public function __construct(private readonly SubscriptionLedger $ledger) {}

    /**
     * How many of the people who signed up in the window reached each step.
     * Each step is counted against all signups on its own, so a step that can
     * be skipped (a run without a workflow) does not distort the ones after it.
     *
     * @return array{from: string, to: string, steps: list<array{step: string, users: int, percent_of_signups: ?float, percent_of_previous: ?float}>}
     */
    public function funnel(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $signups = $this->signups($from, $to);

        $counts = [
            'signed_up' => (clone $signups)->count(),
            'email_verified' => (clone $signups)->whereNotNull('email_verified_at')->count(),
            'created_workflow' => (clone $signups)->whereExists($this->ownedWorkspaceHas('workflows'))->count(),
            'started_run' => (clone $signups)->whereExists($this->ownedWorkspaceHas('runs'))->count(),
            'completed_run' => (clone $signups)->whereExists($this->ownedWorkspaceHas('runs', fn (QueryBuilder $query) => $query->where('runs.status', RunStatus::Completed->value)))->count(),
            'paid' => (clone $signups)->whereExists($this->ownedWorkspaceHas('subscriptions', fn (QueryBuilder $query) => $query->whereIn('subscriptions.stripe_status', ['active', 'past_due', 'canceled'])))->count(),
        ];

        $steps = [];
        $previous = null;

        foreach ($counts as $step => $users) {
            $steps[] = [
                'step' => $step,
                'users' => $users,
                'percent_of_signups' => $counts['signed_up'] === 0 ? null : round($users / $counts['signed_up'] * 100, 1),
                'percent_of_previous' => $previous === null || $previous === 0 ? null : round($users / $previous * 100, 1),
            ];
            $previous = $users;
        }

        return ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'steps' => $steps];
    }

    /**
     * The share of signups whose first completed run came within a day, a week
     * and a month. Only signups old enough to have had that long are counted
     * for each horizon, so recent signups do not drag the rate down.
     *
     * @return array{signups: int, horizons: array<string, array{eligible: int, activated: int, percent: ?float}>}
     */
    public function activation(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $now = CarbonImmutable::now('UTC');

        $users = $this->signups($from, $to)->get(['id', 'created_at']);

        $firstRuns = DB::table('users')
            ->join('workspaces', 'workspaces.owner_id', '=', 'users.id')
            ->join('runs', 'runs.workspace_id', '=', 'workspaces.id')
            ->where('runs.status', RunStatus::Completed->value)
            ->whereIn('users.id', $users->pluck('id'))
            ->groupBy('users.id')
            ->selectRaw('users.id as user_id, min(runs.created_at) as first_run')
            ->pluck('first_run', 'user_id');

        $horizons = [];

        foreach (['24h' => 1, '7d' => 7, '30d' => 30] as $label => $days) {
            $eligible = $users->filter(fn (User $user): bool => $user->created_at->copy()->addDays($days)->lte($now));
            $activated = $eligible->filter(function (User $user) use ($firstRuns, $days): bool {
                $first = $firstRuns[$user->id] ?? null;

                return $first !== null && CarbonImmutable::parse($first)->lte($user->created_at->copy()->addDays($days));
            });

            $horizons[$label] = [
                'eligible' => $eligible->count(),
                'activated' => $activated->count(),
                'percent' => $eligible->isEmpty() ? null : round($activated->count() / $eligible->count() * 100, 1),
            ];
        }

        return ['signups' => $users->count(), 'horizons' => $horizons];
    }

    /**
     * Weekly retention: of those who signed up in a week, the share who ran
     * something in each following week.
     *
     * @return array{cohorts: list<array{week: string, users: int, retention_percent: list<?float>}>}
     */
    public function retention(int $weeks): array
    {
        $now = CarbonImmutable::now('UTC');
        $firstWeek = $now->startOfWeek()->subWeeks($weeks - 1);

        $users = User::query()->where('created_at', '>=', $firstWeek)->get(['id', 'created_at']);

        $activity = DB::table('users')
            ->join('workspaces', 'workspaces.owner_id', '=', 'users.id')
            ->join('runs', 'runs.workspace_id', '=', 'workspaces.id')
            ->whereIn('users.id', $users->pluck('id'))
            ->groupBy('users.id', DB::raw('date(runs.created_at)'))
            ->selectRaw('users.id as user_id, date(runs.created_at) as day')
            ->get()
            ->groupBy('user_id');

        $cohorts = [];

        for ($offset = $weeks - 1; $offset >= 0; $offset--) {
            $start = $now->startOfWeek()->subWeeks($offset);
            $members = $users->filter(fn (User $user): bool => CarbonImmutable::parse($user->created_at)->startOfWeek()->equalTo($start));

            $retention = [];

            for ($k = 0; $k <= $offset; $k++) {
                $weekStart = $start->addWeeks($k);
                $weekEnd = $weekStart->endOfWeek();

                $active = $members->filter(fn (User $user): bool => ($activity[$user->id] ?? collect())
                    ->contains(fn ($row): bool => CarbonImmutable::parse($row->day)->between($weekStart, $weekEnd)));

                $retention[] = $members->isEmpty() ? null : round($active->count() / $members->count() * 100, 1);
            }

            $cohorts[] = ['week' => $start->toDateString(), 'users' => $members->count(), 'retention_percent' => $retention];
        }

        return ['cohorts' => $cohorts];
    }

    /**
     * Signups, activation and paid conversion per discovery source, and signups per referral program.
     *
     * @return array{sources: list<array<string, mixed>>, referrals: array{signups: int, by_program: list<array{program: string, signups: int}>}}
     */
    public function acquisition(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $signups = $this->signups($from, $to);

        $sources = (clone $signups)->get(['id', 'discovery_source'])->groupBy(fn (User $user): string => $user->discovery_source?->value ?? 'unknown');

        $activated = (clone $signups)->whereExists($this->ownedWorkspaceHas('runs', fn (QueryBuilder $query) => $query->where('runs.status', RunStatus::Completed->value)))->pluck('id')->flip();
        $paid = (clone $signups)->whereExists($this->ownedWorkspaceHas('subscriptions', fn (QueryBuilder $query) => $query->whereIn('subscriptions.stripe_status', ['active', 'past_due', 'canceled'])))->pluck('id')->flip();

        $rows = $sources->map(fn ($users, string $source): array => [
            'source' => $source,
            'signups' => $users->count(),
            'activated' => $users->filter(fn (User $user): bool => $activated->has($user->id))->count(),
            'paid' => $users->filter(fn (User $user): bool => $paid->has($user->id))->count(),
            'paid_percent' => round($users->filter(fn (User $user): bool => $paid->has($user->id))->count() / $users->count() * 100, 1),
        ])->sortByDesc('signups')->values()->all();

        $byProgram = Referral::query()
            ->whereBetween('referrals.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->join('referral_programs', 'referral_programs.id', '=', 'referrals.program_id')
            ->groupBy('referral_programs.name')
            ->selectRaw('referral_programs.name as program, count(*) as signups')
            ->orderByDesc('signups')
            ->get()
            ->map(fn ($row): array => ['program' => $row->program, 'signups' => (int) $row->signups])
            ->all();

        return ['sources' => $rows, 'referrals' => ['signups' => array_sum(array_column($byProgram, 'signups')), 'by_program' => $byProgram]];
    }

    /**
     * Daily, weekly and monthly active workspaces (ones that ran something)
     * and users (people who started a run themselves), and how sticky the
     * product is: daily over monthly.
     *
     * @return array<string, mixed>
     */
    public function activeUsers(): array
    {
        $count = fn (string $column, int $days): int => Run::query()
            ->whereNotNull($column)
            ->where('created_at', '>=', now()->subDays($days))
            ->distinct()
            ->count($column);

        $workspaces = ['dau' => $count('workspace_id', 1), 'wau' => $count('workspace_id', 7), 'mau' => $count('workspace_id', 30)];
        $users = ['dau' => $count('triggered_by', 1), 'wau' => $count('triggered_by', 7), 'mau' => $count('triggered_by', 30)];

        return [
            'workspaces' => [...$workspaces, 'stickiness_percent' => $workspaces['mau'] === 0 ? null : round($workspaces['dau'] / $workspaces['mau'] * 100, 1)],
            'users' => [...$users, 'stickiness_percent' => $users['mau'] === 0 ? null : round($users['dau'] / $users['mau'] * 100, 1)],
        ];
    }

    /**
     * Paying workspaces whose activity has dropped sharply, worst first.
     *
     * @return list<array<string, mixed>>
     */
    public function atRisk(int $limit, int $minimumPreviousRuns = 10): array
    {
        $now = CarbonImmutable::now('UTC');
        $paying = collect($this->ledger->payingAt($now))->groupBy('workspace_id');

        if ($paying->isEmpty()) {
            return [];
        }

        $counts = fn (CarbonImmutable $from, CarbonImmutable $to) => Run::query()
            ->whereIn('workspace_id', $paying->keys())
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('workspace_id')
            ->selectRaw('workspace_id, count(*) as total')
            ->pluck('total', 'workspace_id');

        $recent = $counts($now->subDays(7), $now);
        $previous = $counts($now->subDays(14), $now->subDays(7));

        $risky = [];

        foreach ($paying as $workspaceId => $rows) {
            $before = (int) ($previous[$workspaceId] ?? 0);
            $after = (int) ($recent[$workspaceId] ?? 0);

            if ($before < $minimumPreviousRuns || $after >= $before * 0.5) {
                continue;
            }

            $risky[] = [
                'workspace_id' => $workspaceId,
                'name' => $rows->first()['workspace'],
                'mrr' => $rows->sum('mrr'),
                'runs_last_7_days' => $after,
                'runs_previous_7_days' => $before,
                'drop_percent' => round((1 - $after / $before) * 100, 1),
            ];
        }

        usort($risky, fn (array $a, array $b): int => [$b['drop_percent'], $b['mrr']] <=> [$a['drop_percent'], $a['mrr']]);

        return array_slice($risky, 0, $limit);
    }

    /**
     * Free workspaces close to their plan's credit allowance, likely upgrades.
     *
     * @return list<array<string, mixed>>
     */
    public function expansionCandidates(int $limit, float $usageRatio = 0.8): array
    {
        $payingWorkspaces = collect($this->ledger->payingAt(CarbonImmutable::now('UTC')))->pluck('workspace_id')->unique()->all();

        return UsagePeriod::query()
            ->with('workspace:id,name')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->where('credits_limit', '>', 0)
            ->whereNotIn('workspace_id', $payingWorkspaces)
            ->whereRaw('credits_used >= credits_limit * ?', [$usageRatio])
            ->orderByRaw('credits_used * 1.0 / credits_limit desc')
            ->limit($limit)
            ->get()
            ->map(fn (UsagePeriod $period): array => [
                'workspace_id' => $period->workspace_id,
                'name' => $period->workspace?->name,
                'credits_used' => $period->credits_used,
                'credits_limit' => $period->credits_limit,
                'usage_percent' => round($period->credits_used / $period->credits_limit * 100, 1),
                'period_ends_at' => $period->ends_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return Builder<User>
     */
    private function signups(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return User::query()->whereBetween('users.created_at', [$from->startOfDay(), $to->endOfDay()]);
    }

    /**
     * A subquery: the user owns a (not deleted) workspace that has rows in `$table`.
     */
    private function ownedWorkspaceHas(string $table, ?callable $constrain = null): \Closure
    {
        return function (QueryBuilder $query) use ($table, $constrain): void {
            $query->from('workspaces')
                ->select(DB::raw(1))
                ->whereColumn('workspaces.owner_id', 'users.id')
                ->whereNull('workspaces.deleted_at')
                ->whereExists(function (QueryBuilder $inner) use ($table, $constrain): void {
                    $inner->from($table)->select(DB::raw(1))->whereColumn("{$table}.workspace_id", 'workspaces.id');

                    if ($constrain !== null) {
                        $constrain($inner);
                    }
                });
        };
    }
}
