<?php

namespace App\Nodes\Integrations\ZohoCampaigns;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * The people who subscribed to, or unsubscribed from, a mailing list lately, as a
 * reader and as a trigger. Campaigns lists a list's people newest first by status,
 * so a poll reads the newest page and the poll's own dedupe by list and address
 * decides what runs. Campaigns registers its webhooks in its own settings, not
 * through its API, so this trigger polls; the generic Webhook trigger takes a
 * Campaigns webhook for instant delivery.
 */
abstract class AbstractZohoCampaignsSubscriberTriggerNode extends AbstractZohoCampaignsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * The status the list is read by: `active` (subscribed) or `unsub`.
     */
    abstract protected function status(): string;

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_key'],
            'properties' => [
                ...$this->credentialFields(),
                'list_key' => $this->listField(),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $list = $this->key($config, 'list_key', 'The mailing list');
        $subscribers = $this->listAll($run, $config, 'getlistsubscribers', 'list_of_details', ['listkey' => $list, 'status' => $this->status(), 'sort' => 'desc'], $this->limit($config, 50));

        return ['subscribers' => array_map(fn (array $subscriber): array => $this->shapeSubscriber($subscriber, $list), $subscribers), 'count' => count($subscribers)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'subscribers';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['id'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 200];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
