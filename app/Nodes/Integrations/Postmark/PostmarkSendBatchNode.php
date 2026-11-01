<?php

namespace App\Nodes\Integrations\Postmark;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class PostmarkSendBatchNode extends AbstractPostmarkNode
{
    public function type(): string
    {
        return 'postmark_send_batch';
    }

    public function name(): string
    {
        return 'Postmark: Send Batch';
    }

    public function description(): string
    {
        return 'Sends up to 500 different emails in one call and says which went and which were refused.';
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
                'messages' => Field::json('Messages (JSON list)', 'A list of {"From", "To", "Subject", "HtmlBody" or "TextBody"} objects.', 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $messages = array_values((array) ($this->decoded($config['messages'] ?? null, 'The messages') ?? []));

        if ($messages === [] || count($messages) > 500) {
            throw new InvalidArgumentException('Give between 1 and 500 messages.');
        }

        foreach ($messages as $index => $message) {
            $number = $index + 1;

            if (! is_array($message) || blank($message['From'] ?? null) || blank($message['To'] ?? null) || blank($message['Subject'] ?? null) || (blank($message['HtmlBody'] ?? null) && blank($message['TextBody'] ?? null))) {
                throw new InvalidArgumentException("Message {$number} needs From, To, Subject and HtmlBody or TextBody.");
            }
        }

        $results = (array) $this->postmark($run, $config, 'POST', '/email/batch', $messages);
        $ids = [];
        $failed = [];

        foreach (array_values($results) as $index => $result) {
            if (is_array($result) && (int) ($result['ErrorCode'] ?? 0) === 0) {
                $ids[] = $result['MessageID'] ?? null;
            } else {
                $failed[] = ['position' => $index + 1, 'error' => is_array($result) ? ($result['Message'] ?? 'Refused') : 'Refused'];
            }
        }

        return ['sent' => count($ids), 'message_ids' => $ids, 'failed' => $failed];
    }
}
