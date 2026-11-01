<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TelegramSendPollNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_send_poll';
    }

    public function name(): string
    {
        return 'Telegram: Send Poll';
    }

    public function description(): string
    {
        return 'Sends a poll or a quiz with two to ten answers. The result has the poll id to read votes with.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'question', 'options'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'question' => Field::text('Question', 'Up to 300 characters.'),
                'options' => Field::list('Answers', 'Two to ten answers, each up to 100 characters.', 'Yes'),
                'poll_type' => Field::select('Kind', ['regular' => 'Poll', 'quiz' => 'Quiz (one right answer)'], null, 'regular'),
                'is_anonymous' => Field::boolean('Anonymous', 'Voters are not shown. Turn off to start flows from "Poll answered".', true),
                'allows_multiple_answers' => Field::advanced(Field::boolean('Allow several answers', 'Polls only.')),
                'correct_option' => Field::advanced(Field::integer('Right answer (number)', 'For a quiz: which answer is right, counting from 1.', null, 1, 10)),
                'explanation' => Field::advanced(Field::text('Explanation', 'Shown after a quiz answer. Up to 200 characters.')),
                'open_period' => Field::advanced(Field::integer('Close after (seconds)', 'Between 5 and 600. Leave empty to keep it open.', null, 5, 600)),
                ...$this->sendFields(formatting: false, buttons: false),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $options = array_values(array_filter(array_map(fn (mixed $option): string => trim((string) $option), (array) ($this->decoded($config['options'] ?? null) ?? [])), fn (string $option): bool => $option !== ''));
        $quiz = ($config['poll_type'] ?? 'regular') === 'quiz';
        $question = $this->requiredString($config, 'question', 'The question');

        if (mb_strlen($question) > 300) {
            throw new InvalidArgumentException('The question is over Telegram\'s limit of 300 characters.');
        }

        if (count($options) < 2 || count($options) > 10) {
            throw new InvalidArgumentException('A poll takes between 2 and 10 answers.');
        }

        $correct = null;

        if ($quiz) {
            $correct = (int) ($config['correct_option'] ?? 0);

            if ($correct < 1 || $correct > count($options)) {
                throw new InvalidArgumentException('A quiz needs the number of the right answer, between 1 and '.count($options).'.');
            }
        }

        $message = (array) $this->call($run, $config, 'sendPoll', [
            'chat_id' => $chat,
            'question' => $question,
            'options' => array_map(fn (string $option): array => ['text' => $option], $options),
            'type' => $quiz ? 'quiz' : 'regular',
            'is_anonymous' => filter_var($config['is_anonymous'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'allows_multiple_answers' => ! $quiz && filter_var($config['allows_multiple_answers'] ?? false, FILTER_VALIDATE_BOOLEAN) ? true : null,
            'correct_option_id' => $correct === null ? null : $correct - 1,
            'explanation' => $quiz && filled($config['explanation'] ?? null) ? (string) $config['explanation'] : null,
            'open_period' => is_numeric($config['open_period'] ?? null) ? (int) $config['open_period'] : null,
            ...$this->sendOptions($config, formatting: false),
        ]);

        return [...$this->shapeMessage($message), 'poll_id' => data_get($message, 'poll.id'), 'question' => data_get($message, 'poll.question')];
    }
}
