<?php

namespace App\Nodes\Integrations\Typeform;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TypeformListWebhooksNode extends AbstractTypeformNode
{
    public function type(): string
    {
        return 'typeform_list_webhooks';
    }

    public function name(): string
    {
        return 'Typeform: List Webhooks';
    }

    public function description(): string
    {
        return 'Lists the webhooks set on a form, including the ones this app manages for instant triggers.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['form_id'],
            'properties' => [
                ...$this->credentialFields(),
                'form_id' => $this->formField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $form = $this->formId($config);
        $items = array_values(array_filter((array) data_get((array) $this->typeform($run, $config, 'GET', "/forms/{$form}/webhooks"), 'items', []), 'is_array'));

        return ['webhooks' => array_map(fn (array $hook): array => ['tag' => $hook['tag'] ?? null, 'url' => $hook['url'] ?? null, 'enabled' => $hook['enabled'] ?? null, 'verify_ssl' => $hook['verify_ssl'] ?? null, 'created_at' => $hook['created_at'] ?? null], $items), 'count' => count($items), 'form_id' => $form];
    }
}
