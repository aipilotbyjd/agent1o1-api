<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TelegramAnswerCallbackQueryNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_answer_callback_query';
    }

    public function name(): string
    {
        return 'Telegram: Answer Button Press';
    }

    public function description(): string
    {
        return 'Answers a button press so the button stops showing a spinner, optionally with a short notice or an alert.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['callback_query_id'],
            'properties' => [
                ...$this->credentialFields(),
                'callback_query_id' => Field::text('Button press id', 'The callback_query_id from the "Button pressed" trigger.'),
                'text' => Field::text('Notice', 'A short text shown to the person. Up to 200 characters.'),
                'show_alert' => Field::boolean('Show as an alert', 'A pop-up they must dismiss, instead of a brief toast.'),
                'url' => Field::advanced(Field::text('Open a link', 'A link to open, for a game or a t.me link to the bot.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $text = (string) ($config['text'] ?? '');

        if (mb_strlen($text) > 200) {
            throw new InvalidArgumentException('The notice is over Telegram\'s limit of 200 characters.');
        }

        $this->call($run, $config, 'answerCallbackQuery', [
            'callback_query_id' => $this->requiredString($config, 'callback_query_id', 'The button press id'),
            'text' => $text !== '' ? $text : null,
            'show_alert' => filter_var($config['show_alert'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'url' => filled($config['url'] ?? null) ? (string) $config['url'] : null,
        ]);

        return ['answered' => true, 'callback_query_id' => (string) $config['callback_query_id']];
    }
}
