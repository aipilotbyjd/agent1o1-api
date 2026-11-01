<?php

namespace App\Nodes\Integrations\Discord;

use App\Nodes\Support\Field;

class DiscordNewButtonClickNode extends AbstractDiscordInteractionTriggerNode
{
    public function type(): string
    {
        return 'discord_new_button_click';
    }

    public function name(): string
    {
        return 'Discord: Button Clicked';
    }

    public function description(): string
    {
        return 'Starts a flow when someone presses a button (or picks from a menu) under a message the bot sent. Give the button a custom_id in "Send Message". Needs instant delivery (the bot\'s Interactions URL), and the connection must hold the Application ID and Public key.';
    }

    public function kind(): string
    {
        return 'button_click';
    }

    public function triggerName(): string
    {
        return 'Discord: Button clicked';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone presses a button under a message the bot sent.';
    }

    protected function filterFields(): array
    {
        return [
            'custom_id' => Field::text('Only this button', 'The button\'s custom_id. End with * to match a prefix, e.g. approve:*. Leave empty for any button.', 'approve:*'),
            'channel_id' => Field::advanced(Field::text('Only in this channel', 'A channel id.')),
        ];
    }

    protected function matches(array $item, array $config): bool
    {
        $expected = trim((string) ($config['custom_id'] ?? ''));
        $id = (string) ($item['custom_id'] ?? '');
        $channel = trim((string) ($config['channel_id'] ?? ''));

        return ($expected === '' || (str_ends_with($expected, '*') ? str_starts_with($id, substr($expected, 0, -1)) : $id === $expected))
            && ($channel === '' || (string) ($item['channel_id'] ?? '') === $channel);
    }
}
