<?php

namespace App\Nodes\Integrations\Postmark;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class PostmarkGetStatsNode extends AbstractPostmarkNode
{
    public function type(): string
    {
        return 'postmark_get_stats';
    }

    public function name(): string
    {
        return 'Postmark: Get Email Stats';
    }

    public function description(): string
    {
        return 'Reads the totals sent, bounced, spam complaints, opens and clicks for a date range, optionally for one tag.';
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
                'from_date' => Field::text('From', 'A date, e.g. 2026-03-01. Empty is the last 7 days.', '2026-03-01'),
                'to_date' => Field::advanced(Field::text('To', 'A date. Empty is today.', '2026-03-31')),
                'tag' => Field::advanced(Field::text('Tag')),
                'stream' => $this->streamField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $from = filled($config['from_date'] ?? null) ? Carbon::parse((string) $config['from_date']) : now()->subDays(7);
        $to = filled($config['to_date'] ?? null) ? Carbon::parse((string) $config['to_date']) : now();

        if ($to->lt($from)) {
            throw new InvalidArgumentException('"To" is before "From".');
        }

        $stats = (array) $this->postmark($run, $config, 'GET', '/stats/outbound', query: ['fromdate' => $from->toDateString(), 'todate' => $to->toDateString(), 'tag' => filled($config['tag'] ?? null) ? trim((string) $config['tag']) : null, 'messagestream' => filled($config['stream'] ?? null) ? trim((string) $config['stream']) : null]);

        return [
            'sent' => $stats['Sent'] ?? 0,
            'bounced' => $stats['Bounced'] ?? 0,
            'bounce_rate' => $stats['BounceRate'] ?? 0,
            'spam_complaints' => $stats['SpamComplaints'] ?? 0,
            'opens' => $stats['Opens'] ?? 0,
            'unique_opens' => $stats['UniqueOpens'] ?? 0,
            'clicks' => $stats['TotalClicks'] ?? 0,
            'unique_clicks' => $stats['UniqueLinksClicked'] ?? 0,
            'tracked' => $stats['TotalTrackedLinksSent'] ?? 0,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }
}
