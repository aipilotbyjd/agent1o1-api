<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KitUntagSubscriberNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_untag_subscriber';
    }

    public function name(): string
    {
        return 'Kit: Remove Tag from Subscriber';
    }

    public function description(): string
    {
        return 'Takes a tag off a subscriber.';
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
        $id = $this->subscriberId($run, $config);

        $this->kit($run, $config, 'DELETE', "/tags/{$tag}/subscribers/{$id}");

        return ['untagged' => true, 'tag_id' => $tag, 'subscriber_id' => $id];
    }
}
