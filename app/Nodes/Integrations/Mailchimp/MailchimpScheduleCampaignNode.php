<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class MailchimpScheduleCampaignNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_schedule_campaign';
    }

    public function name(): string
    {
        return 'Mailchimp: Schedule Campaign';
    }

    public function description(): string
    {
        return 'Schedules a draft campaign to go out at a time on a quarter hour (Mailchimp rounds to :00, :15, :30, :45).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['campaign_id', 'send_at'],
            'properties' => [
                ...$this->credentialFields(),
                'campaign_id' => $this->campaignField(),
                'send_at' => Field::datetime('Send at', 'It must be in the future.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->campaignId($config);
        $at = Carbon::parse($this->requiredString($config, 'send_at', 'The send time'))->utc();

        if ($at->isPast()) {
            throw new InvalidArgumentException('The send time must be in the future.');
        }

        $this->mailchimp($run, $config, 'POST', "/campaigns/{$id}/actions/schedule", ['schedule_time' => $at->format('Y-m-d\TH:i:sP')]);

        return ['scheduled' => true, 'campaign_id' => $id, 'send_at' => $at->toIso8601String()];
    }
}
