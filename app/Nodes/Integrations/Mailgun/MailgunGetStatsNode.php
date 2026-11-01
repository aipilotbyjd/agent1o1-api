<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class MailgunGetStatsNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_get_stats';
    }

    public function name(): string
    {
        return 'Mailgun: Get Email Stats';
    }

    public function description(): string
    {
        return 'Reads the totals accepted, delivered, failed, opened, clicked, unsubscribed and complained for a date range, by day, week or month.';
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
                'resolution' => Field::advanced(Field::select('Group by', ['day' => 'Day', 'hour' => 'Hour', 'month' => 'Month'], null, 'day')),
                'domain' => Field::advanced(Field::text('Domain', 'Another domain of the account. Empty uses the connection\'s.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $domain = $this->domain($run, $config);
        $start = filled($config['start_date'] ?? null) ? Carbon::parse((string) $config['start_date'])->utc() : now()->subDays(7);
        $end = filled($config['end_date'] ?? null) ? Carbon::parse((string) $config['end_date'])->utc() : now();

        if ($end->lt($start)) {
            throw new InvalidArgumentException('"To" is before "From".');
        }

        $events = ['accepted', 'delivered', 'failed', 'opened', 'clicked', 'unsubscribed', 'complained'];
        $found = (array) $this->mailgun($run, $config, 'GET', "/v3/{$domain}/stats/total", query: ['event' => $events, 'start' => $start->timestamp, 'end' => $end->timestamp, 'resolution' => in_array($config['resolution'] ?? 'day', ['hour', 'day', 'month'], true) ? ($config['resolution'] ?? 'day') : 'day']);
        $totals = array_fill_keys(['accepted', 'delivered', 'failed', 'opened', 'clicked', 'unsubscribed', 'complained'], 0);
        $periods = [];

        foreach (array_filter((array) ($found['stats'] ?? []), 'is_array') as $row) {
            $period = ['time' => $row['time'] ?? null];

            foreach (array_keys($totals) as $event) {
                $count = $event === 'failed'
                    ? (int) data_get($row, 'failed.permanent.total', 0) + (int) data_get($row, 'failed.temporary.total', 0)
                    : (int) data_get($row, "{$event}.total", 0);
                $period[$event] = $count;
                $totals[$event] += $count;
            }

            $periods[] = $period;
        }

        return ['periods' => $periods, 'totals' => (object) $totals, 'count' => count($periods)];
    }
}
