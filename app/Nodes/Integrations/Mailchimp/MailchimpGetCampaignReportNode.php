<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MailchimpGetCampaignReportNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_get_campaign_report';
    }

    public function name(): string
    {
        return 'Mailchimp: Get Campaign Report';
    }

    public function description(): string
    {
        return 'Gets how a sent campaign did: opens, clicks, bounces, unsubscribes and revenue.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['campaign_id'],
            'properties' => [
                ...$this->credentialFields(),
                'campaign_id' => $this->campaignField('A sent campaign.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $report = (array) $this->mailchimp($run, $config, 'GET', '/reports/'.$this->campaignId($config));

        return [
            'campaign_id' => $report['id'] ?? null,
            'title' => $report['campaign_title'] ?? null,
            'subject' => $report['subject_line'] ?? null,
            'emails_sent' => $report['emails_sent'] ?? null,
            'opens' => data_get($report, 'opens.opens_total'),
            'unique_opens' => data_get($report, 'opens.unique_opens'),
            'open_rate' => data_get($report, 'opens.open_rate'),
            'clicks' => data_get($report, 'clicks.clicks_total'),
            'unique_clicks' => data_get($report, 'clicks.unique_clicks'),
            'click_rate' => data_get($report, 'clicks.click_rate'),
            'hard_bounces' => data_get($report, 'bounces.hard_bounces'),
            'soft_bounces' => data_get($report, 'bounces.soft_bounces'),
            'unsubscribed' => $report['unsubscribed'] ?? null,
            'abuse_reports' => $report['abuse_reports'] ?? null,
            'revenue' => data_get($report, 'ecommerce.total_revenue'),
            'send_time' => $report['send_time'] ?? null,
        ];
    }
}
