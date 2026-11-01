<?php

namespace App\Nodes\Integrations\YouTube;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class YouTubeListSubscribersNode extends AbstractYouTubeNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'google_youtube_list_subscribers';
    }

    public function name(): string
    {
        return 'YouTube: List Subscribers';
    }

    public function description(): string
    {
        return "Lists the people subscribed to the connected account's channel. Only subscribers who made their subscriptions public are listed.";
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
                'max_results' => Field::integer('Max results', null, 50, 1, 50),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->get($run, '/subscriptions', $config, [
            'part' => 'snippet,subscriberSnippet',
            'mySubscribers' => 'true',
            'maxResults' => $config['max_results'] ?? 50,
        ]);

        return ['subscribers' => array_map(fn (array $item): array => $this->subscription($item, true), (array) ($response['items'] ?? []))];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'subscribers';
    }

    /**
     * A subscriber's channel id is their stable id.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['channel_id'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'YouTube: New subscriber';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when someone subscribes to your channel. Only public subscribers are visible to YouTube\'s API.';
    }

    /**
     * Read the widest page, so a burst between two polls isn't cut off.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'max_results' => 50];
    }

    /**
     * Newest first, as the list is returned.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }
}
