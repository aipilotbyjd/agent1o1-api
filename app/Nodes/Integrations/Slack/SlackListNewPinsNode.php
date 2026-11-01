<?php

namespace App\Nodes\Integrations\Slack;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * The messages pinned in a channel, most recently pinned first. As a flow
 * trigger it starts a run when something new is pinned; Slack's `pin_added`
 * event brings the check forward, so it does not wait for the timer.
 */
class SlackListNewPinsNode extends AbstractSlackNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'slack_list_new_pins';
    }

    public function name(): string
    {
        return 'Slack: List Pinned Messages';
    }

    public function description(): string
    {
        return 'Lists the messages pinned in a channel, most recently pinned first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel'],
            'properties' => [
                ...$this->credentialFields(),
                'channel' => Field::dynamic('Channel', 'slack.channels', 'The channel to watch. Pick one, or enter a channel ID.', 'C0123456789'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->get($run, 'pins.list', $config, ['channel' => $config['channel']]);
        $pins = [];

        foreach ((array) ($response['items'] ?? []) as $pin) {
            if (! is_array($pin) || ($pin['type'] ?? null) !== 'message' || ! isset($pin['message']['ts'])) {
                continue;
            }

            $pins[] = array_filter([
                'channel' => $config['channel'],
                'ts' => $pin['message']['ts'],
                'text' => $pin['message']['text'] ?? null,
                'user' => $pin['message']['user'] ?? null,
                'permalink' => $pin['message']['permalink'] ?? null,
                'pinned_by' => $pin['created_by'] ?? null,
                'pinned_at' => $pin['created'] ?? null,
            ], fn (mixed $value): bool => $value !== null);
        }

        // Slack's order is not promised, so put the latest pins first.
        usort($pins, fn (array $a, array $b): int => ($b['pinned_at'] ?? 0) <=> ($a['pinned_at'] ?? 0));

        return ['pins' => $pins];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'pins';
    }

    /**
     * A message and the moment it was pinned: pinning it again later is new.
     */
    public function triggerItemId(array $item): string
    {
        return ($item['ts'] ?? '').':'.($item['pinned_at'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Slack: Message pinned';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone pins a message in the channel.';
    }

    public function triggerPollMinutes(): int
    {
        return 15;
    }

    /**
     * Newest pins first.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
