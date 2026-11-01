<?php

namespace App\Services\Workflows\Engine;

use App\Enums\NodeRunStatus;
use App\Models\Runs\Run;
use App\Models\Workflows\WorkflowEdge;

/**
 * What a Merge Results (Join Paths) step hands on: the outputs of the
 * branches that arrived, so the step after it can use them together instead
 * of reaching back into each branch by name.
 *
 * A join only runs once every branch feeding it is settled, so each source is
 * in exactly one of three states: it completed (its output is here), it never
 * ran because its branch was not taken, or it failed and the run carried on.
 */
final class JoinResults
{
    public const string NONE = 'none';

    public const string BY_STEP = 'by_step';

    public const string LIST = 'list';

    /**
     * The most a merged result will hold, so a join over big outputs (files,
     * long documents) cannot bloat the run. Branches past it are named under
     * `omitted`; their outputs stay on their own steps.
     */
    public const int MAX_BYTES = 1_000_000;

    /**
     * A join that predates this setting keeps its old behaviour: it waits and
     * returns nothing.
     *
     * @param  array<string, mixed>  $config
     */
    public static function mode(array $config): string
    {
        $mode = (string) ($config['combine'] ?? self::NONE);

        return in_array($mode, [self::NONE, self::BY_STEP, self::LIST], true) ? $mode : self::NONE;
    }

    /**
     * @param  array{nodes?: array<int, array<string, mixed>>, edges?: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     * @return array<string, mixed>
     */
    public function combine(Run $run, string $key, array $graph, string $mode): array
    {
        if ($mode === self::NONE) {
            return [];
        }

        $sourceKeys = collect($graph['edges'] ?? [])
            ->filter(fn (array $edge): bool => $edge['to'] === $key && $edge['condition'] !== WorkflowEdge::ERROR_CONDITION)
            ->pluck('from')
            ->unique()
            ->values();

        $nodeRuns = $run->nodeRuns()->whereIn('key', $sourceKeys)->get()->keyBy('key');

        $results = [];
        $arrived = [];
        $skipped = [];
        $failed = [];
        $omitted = [];
        $bytes = 0;

        foreach ($sourceKeys as $source) {
            $nodeRun = $nodeRuns->get($source);

            if ($nodeRun === null || $nodeRun->status === NodeRunStatus::Skipped) {
                $skipped[] = $source;

                continue;
            }

            if ($nodeRun->status === NodeRunStatus::Failed) {
                $failed[] = $source;

                continue;
            }

            $arrived[] = $source;
            $output = $nodeRun->output ?? [];
            $size = strlen((string) json_encode($output));

            if ($bytes + $size > self::MAX_BYTES) {
                $omitted[] = $source;

                continue;
            }

            $bytes += $size;
            $results[$source] = $output;
        }

        return [
            'results' => $mode === self::LIST ? array_values($results) : $results,
            'count' => count($arrived),
            'arrived' => $arrived,
            'skipped' => $skipped,
            'failed' => $failed,
            'omitted' => $omitted,
        ];
    }
}
