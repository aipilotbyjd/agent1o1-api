<?php

namespace App\Nodes\Integrations\Typeform;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TypeformUpdateFormNode extends AbstractTypeformNode
{
    public function type(): string
    {
        return 'typeform_update_form';
    }

    public function name(): string
    {
        return 'Typeform: Update Form';
    }

    public function description(): string
    {
        return 'Changes a form\'s title, language, visibility or workspace, or applies your own JSON Patch operations. Only what is filled in changes.';
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
                'title' => Field::text('New title'),
                'language' => Field::advanced(Field::text('Language', 'A language code such as en, fr, de.', 'en')),
                'is_public' => Field::advanced(Field::select('Public', ['unchanged' => 'Leave as it is', 'yes' => 'Public', 'no' => 'Private'], null, 'unchanged')),
                'workspace_id' => Field::advanced(Field::dynamic('Move to workspace', 'typeform.workspaces', null, 'My workspace')),
                'patch' => Field::advanced(Field::json('JSON Patch operations', 'For anything else, e.g. [{"op": "replace", "path": "/settings/show_progress_bar", "value": true}].', 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->formId($config);
        $operations = [];
        $public = (string) ($config['is_public'] ?? 'unchanged');

        foreach (['title' => '/title', 'language' => '/settings/language'] as $key => $path) {
            if (filled($config[$key] ?? null)) {
                $operations[] = ['op' => 'replace', 'path' => $path, 'value' => (string) $config[$key]];
            }
        }

        if (in_array($public, ['yes', 'no'], true)) {
            $operations[] = ['op' => 'replace', 'path' => '/settings/is_public', 'value' => $public === 'yes'];
        }

        if (filled($config['workspace_id'] ?? null)) {
            $operations[] = ['op' => 'replace', 'path' => '/workspace', 'value' => ['href' => $this->host($run, $config).'/workspaces/'.rawurlencode((string) $config['workspace_id'])]];
        }

        foreach ((array) ($this->decoded($config['patch'] ?? null, 'The patch operations') ?? []) as $operation) {
            if (! is_array($operation) || ! in_array($operation['op'] ?? null, ['add', 'remove', 'replace'], true) || ! filled($operation['path'] ?? null)) {
                throw new InvalidArgumentException('Every patch operation needs an op (add, remove or replace) and a path.');
            }

            $operations[] = $operation;
        }

        if ($operations === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        $this->typeform($run, $config, 'PATCH', "/forms/{$id}", $operations);

        return $this->shapeForm((array) $this->typeform($run, $config, 'GET', "/forms/{$id}"));
    }
}
