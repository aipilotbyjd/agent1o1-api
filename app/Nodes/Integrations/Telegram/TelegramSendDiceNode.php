<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramSendDiceNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_send_dice';
    }

    public function name(): string
    {
        return 'Telegram: Send Dice';
    }

    public function description(): string
    {
        return 'Sends an animated die, dart, ball, bowling pin or slot machine with a random result.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'emoji' => Field::select('Kind', ['🎲' => 'Dice', '🎯' => 'Dart', '🏀' => 'Basketball', '⚽' => 'Football', '🎳' => 'Bowling', '🎰' => 'Slot machine'], null, '🎲'),
                ...$this->sendFields(formatting: false, buttons: false),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $message = (array) $this->call($run, $config, 'sendDice', ['chat_id' => $chat, 'emoji' => (string) ($config['emoji'] ?? '🎲'), ...$this->sendOptions($config, formatting: false)]);

        return [...$this->shapeMessage($message), 'emoji' => data_get($message, 'dice.emoji'), 'value' => data_get($message, 'dice.value')];
    }
}
