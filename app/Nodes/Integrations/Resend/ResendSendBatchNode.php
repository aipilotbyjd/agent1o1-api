<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ResendSendBatchNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_send_batch';
    }

    public function name(): string
    {
        return 'Resend: Send Batch';
    }

    public function description(): string
    {
        return 'Sends up to 100 different emails in one call (each with its own recipient and content). Does not take attachments or scheduling.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['messages'],
            'properties' => [
                ...$this->credentialFields(),
                'messages' => Field::json('Messages (JSON list)', 'A list of {"from", "to", "subject", "html" or "text"} objects.', 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $messages = array_values((array) ($this->decoded($config['messages'] ?? null, 'The messages') ?? []));

        if ($messages === [] || count($messages) > 100) {
            throw new InvalidArgumentException('Give between 1 and 100 messages.');
        }

        foreach ($messages as $index => $message) {
            $number = $index + 1;

            if (! is_array($message) || blank($message['from'] ?? null) || blank($message['subject'] ?? null) || blank($message['to'] ?? null) || (blank($message['html'] ?? null) && blank($message['text'] ?? null))) {
                throw new InvalidArgumentException("Message {$number} needs from, to, subject and html or text.");
            }

            foreach ((array) $message['to'] as $address) {
                $this->email(['e' => $address], 'e');
            }
        }

        $sent = (array) $this->resend($run, $config, 'POST', '/emails/batch', $messages);
        $ids = array_values(array_filter(array_map(fn (mixed $item): ?string => is_array($item) ? ($item['id'] ?? null) : null, (array) ($sent['data'] ?? []))));

        return ['sent' => count($ids), 'ids' => $ids];
    }
}
