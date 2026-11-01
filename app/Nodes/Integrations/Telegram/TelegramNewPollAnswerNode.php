<?php

namespace App\Nodes\Integrations\Telegram;

use App\Nodes\Support\Field;

class TelegramNewPollAnswerNode extends AbstractTelegramTriggerNode
{
    public function type(): string
    {
        return 'telegram_new_poll_answer';
    }

    public function name(): string
    {
        return 'Telegram: Poll Answered';
    }

    public function description(): string
    {
        return 'Starts a flow when someone votes in a non-anonymous poll the bot sent. Needs instant delivery.';
    }

    public function kind(): string
    {
        return 'poll_answer';
    }

    public function triggerName(): string
    {
        return 'Telegram: Poll answered';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone votes in a poll the bot sent.';
    }

    protected function filterFields(): array
    {
        return [
            'poll_id' => Field::text('Only this poll', 'The poll\'s id, from the Send Poll step. Leave empty for every poll.'),
        ];
    }

    protected function wants(array $item, array $config): bool
    {
        $poll = trim((string) ($config['poll_id'] ?? ''));

        return ($item['kind'] ?? null) === $this->kind() && ($poll === '' || (string) ($item['poll_id'] ?? '') === $poll);
    }
}
