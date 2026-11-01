<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class MailchimpListCampaignsNode extends AbstractMailchimpNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'mailchimp_list_campaigns';
    }

    public function name(): string
    {
        return 'Mailchimp: List Campaigns';
    }

    public function description(): string
    {
        return 'Lists campaigns, newest first, optionally by status or audience. As a trigger it starts the flow for each campaign that gets sent.';
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
                'status' => Field::select('Status', ['sent' => 'Sent', 'any' => 'Any', 'save' => 'Draft', 'paused' => 'Paused', 'schedule' => 'Scheduled', 'sending' => 'Sending'], null, 'sent'),
                'list_id' => Field::advanced($this->audienceField('Only campaigns to this audience.')),
                'limit' => Field::integer('Max results', null, 20, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = (string) ($config['status'] ?? 'sent');
        $sent = $status === 'sent';
        $campaigns = $this->listAll($run, $config, '/campaigns', 'campaigns', [
            'status' => $status !== 'any' && $status !== '' ? $status : null,
            'list_id' => filled($config['list_id'] ?? null) ? $this->listId($config) : null,
            'sort_field' => $sent ? 'send_time' : 'create_time',
            'sort_dir' => 'DESC',
        ], $this->limit($config, 20));

        return ['campaigns' => array_map($this->shapeCampaign(...), $campaigns), 'count' => count($campaigns)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'campaigns';
    }

    public function triggerName(): string
    {
        return 'Mailchimp: Campaign sent';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a Mailchimp campaign has been sent.';
    }

    /**
     * Only campaigns that went out.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'status' => 'sent', 'limit' => 50];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
