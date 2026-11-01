<?php

namespace App\Nodes\Integrations\GoogleForms;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleFormsGetResponseNode extends AbstractGoogleFormsNode
{
    public function type(): string
    {
        return 'google_forms_get_response';
    }

    public function name(): string
    {
        return 'Google Forms: Get Response';
    }

    public function description(): string
    {
        return 'Reads one response, with every answer next to its question.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['form_id', 'response_id'],
            'properties' => [
                ...$this->credentialFields(),
                'form_id' => $this->formField(),
                'response_id' => Field::text('Response ID', 'The "response_id" from List Responses or a trigger.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->get($run, $this->formPath($config).'/responses/'.rawurlencode((string) $config['response_id']), $config);

        return $this->response($response, $this->questionTitles($this->fetchForm($run, $config)));
    }
}
