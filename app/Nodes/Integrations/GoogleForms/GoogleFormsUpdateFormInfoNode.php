<?php

namespace App\Nodes\Integrations\GoogleForms;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GoogleFormsUpdateFormInfoNode extends AbstractGoogleFormsNode
{
    public function type(): string
    {
        return 'google_forms_update_form_info';
    }

    public function name(): string
    {
        return 'Google Forms: Update Form Info';
    }

    public function description(): string
    {
        return 'Changes a form\'s title or description. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['form_id'],
            'properties' => [
                ...$this->credentialFields(),
                'form_id' => $this->formField(),
                'title' => Field::text('Title', 'Leave empty to keep it.'),
                'description' => Field::textarea('Description', 'Leave empty to keep it.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $info = [];

        foreach (['title', 'description'] as $key) {
            if (trim((string) ($config[$key] ?? '')) !== '') {
                $info[$key] = trim((string) $config[$key]);
            }
        }

        if ($info === []) {
            throw new InvalidArgumentException('Change at least the title or the description.');
        }

        $this->post($run, $this->formPath($config).':batchUpdate', $config, ['requests' => [
            ['updateFormInfo' => ['info' => $info, 'updateMask' => implode(',', array_keys($info))]],
        ]]);

        return ['updated' => true, 'form_id' => (string) $config['form_id'], ...$info];
    }
}
