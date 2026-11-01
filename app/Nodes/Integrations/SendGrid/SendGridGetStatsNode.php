<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class SendGridGetStatsNode extends AbstractSendGridNode
{
    public function type(): string
    {
        return 'sendgrid_get_stats';
    }

    public function name(): string
    {
        return 'SendGrid: Get Email Stats';
    }

    public function description(): string
    {
        return 'Reads the email stats (delivered, opens, clicks, bounces...) for a date range, by day, week or month, with totals.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'start_date' => Field::text('From', 'A date, e.g. 2026-03-01. Empty is the last 7 days.', '2026-03-01'),
                'end_date' => Field::advanced(Field::text('To', 'A date. Empty is today.', '2026-03-31')),
                'aggregated_by' => Field::advanced(Field::select('Group by', ['day' => 'Day', 'week' => 'Week', 'month' => 'Month'], null, 'day')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $start = filled($config['start_date'] ?? null) ? Carbon::parse((string) $config['start_date']) : now()->subDays(7);
        $end = filled($config['end_date'] ?? null) ? Carbon::parse((string) $config['end_date']) : now();

        if ($end->lt($start)) {
            throw new InvalidArgumentException('"To" is before "From".');
        }

        $days = (array) $this->sendgrid($run, $config, 'GET', '/stats', query: ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'aggregated_by' => in_array($config['aggregated_by'] ?? 'day', ['day', 'week', 'month'], true) ? ($config['aggregated_by'] ?? 'day') : 'day']);
        $totals = [];
        $periods = [];

        foreach (array_filter($days, 'is_array') as $day) {
            $metrics = (array) data_get($day, 'stats.0.metrics', []);
            $periods[] = ['date' => $day['date'] ?? null, ...$metrics];

            foreach ($metrics as $name => $value) {
                $totals[$name] = ($totals[$name] ?? 0) + (int) $value;
            }
        }

        return ['periods' => $periods, 'totals' => (object) $totals, 'count' => count($periods)];
    }
}
