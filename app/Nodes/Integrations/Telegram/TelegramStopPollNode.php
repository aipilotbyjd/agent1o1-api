<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramStopPollNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_stop_poll';
    }

    public function name(): string
    {
        return 'Telegram: Stop Poll';
    }

    public function description(): string
    {
        return 'Closes a poll the bot sent and returns the final votes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'message_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'message_id' => Field::integer('Poll message id', 'The id of the message the poll was sent in.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $poll = (array) $this->call($run, $config, 'stopPoll', ['chat_id' => $chat, 'message_id' => $this->requiredInt($config, 'message_id', 'The poll message id')]);

        return [
            'poll_id' => $poll['id'] ?? null,
            'question' => $poll['question'] ?? null,
            'total_voters' => $poll['total_voter_count'] ?? null,
            'is_closed' => $poll['is_closed'] ?? true,
            'options' => array_map(fn (array $option): array => ['text' => $option['text'] ?? null, 'votes' => $option['voter_count'] ?? 0], array_filter((array) ($poll['options'] ?? []), 'is_array')),
        ];
    }
}
