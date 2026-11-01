<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KitAddSubscriberToFormNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_add_subscriber_to_form';
    }

    public function name(): string
    {
        return 'Kit: Add Subscriber to Form';
    }

    public function description(): string
    {
        return 'Signs a subscriber up through a form, as if they had submitted it (so its welcome flow runs).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['form_id', 'subscriber'],
            'properties' => [
                ...$this->credentialFields(),
                'form_id' => Field::dynamic('Form', 'kit.forms', 'Pick a form.', 'Newsletter signup', type: 'integer'),
                'subscriber' => Field::text('Subscriber', 'Their email address or Kit id.', 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $form = $this->numericId($config, 'form_id', 'The form');
        $value = trim((string) ($config['subscriber'] ?? ''));

        if (ctype_digit($value)) {
            $this->kit($run, $config, 'POST', "/forms/{$form}/subscribers/{$value}");
        } else {
            $this->kit($run, $config, 'POST', "/forms/{$form}/subscribers", ['email_address' => $this->email($config, 'subscriber')]);
        }

        return ['added' => true, 'form_id' => $form, 'subscriber' => $value];
    }
}
