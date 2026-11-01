<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KitTagSubscriberNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_tag_subscriber';
    }

    public function name(): string
    {
        return 'Kit: Tag Subscriber';
    }

    public function description(): string
    {
        return 'Puts a tag on a subscriber, adding them to Kit first if the email is new.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['tag_id', 'subscriber'],
            'properties' => [
                ...$this->credentialFields(),
                'tag_id' => $this->tagField(),
                'subscriber' => Field::text('Subscriber', 'Their email address or Kit id.', 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tag = $this->numericId($config, 'tag_id', 'The tag');
        $value = trim((string) ($config['subscriber'] ?? ''));

        // An email goes straight in (Kit makes the subscriber if needed); an id is tagged as it is.
        if (ctype_digit($value)) {
            $this->kit($run, $config, 'POST', "/tags/{$tag}/subscribers/{$value}");
        } else {
            $this->kit($run, $config, 'POST', "/tags/{$tag}/subscribers", ['email_address' => $this->email($config, 'subscriber')]);
        }

        return ['tagged' => true, 'tag_id' => $tag, 'subscriber' => $value];
    }
}
