<?php

namespace App\Nodes\Integrations\Telegram;

use App\Nodes\Support\Field;

class TelegramNewCallbackQueryNode extends AbstractTelegramTriggerNode
{
    public function type(): string
    {
        return 'telegram_new_callback_query';
    }

    public function name(): string
    {
        return 'Telegram: Button Pressed';
    }

    public function description(): string
    {
        return 'Starts a flow when someone presses a button with callback data under a message the bot sent. Answer it with "Answer Button Press" so the button stops spinning. Needs instant delivery.';
    }

    public function kind(): string
    {
        return 'callback_query';
    }

    public function triggerName(): string
    {
        return 'Telegram: Button pressed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone presses an inline button under a message the bot sent.';
    }

    protected function filterFields(): array
    {
        return [
            ...parent::filterFields(),
            'callback_data' => Field::text('Only this button', 'The button\'s data. End with * to match a prefix, e.g. approve:*. Leave empty for any button.', 'approve:*'),
        ];
    }

    protected function wants(array $item, array $config): bool
    {
        if (! parent::wants($item, $config)) {
            return false;
        }

        $expected = trim((string) ($config['callback_data'] ?? ''));
        $data = (string) ($item['data'] ?? '');

        return $expected === '' || (str_ends_with($expected, '*') ? str_starts_with($data, substr($expected, 0, -1)) : $data === $expected);
    }
}
