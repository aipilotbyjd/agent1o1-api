<?php

namespace App\Nodes\Integrations\Typeform;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TypeformCreateFormNode extends AbstractTypeformNode
{
    public function type(): string
    {
        return 'typeform_create_form';
    }

    public function name(): string
    {
        return 'Typeform: Create Form';
    }

    public function description(): string
    {
        return 'Creates a form from a title and a list of questions in Typeform\'s own JSON (type, title, properties).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                ...$this->credentialFields(),
                'title' => Field::text('Title', null, 'Customer survey'),
                'fields' => Field::json('Questions', 'A list such as [{"type": "short_text", "title": "Your name?"}, {"type": "email", "title": "Your email?"}].', 'array'),
                'workspace_id' => Field::dynamic('Workspace', 'typeform.workspaces', 'Create it in this workspace. Leave empty for the default one.', 'My workspace'),
                'hidden' => Field::advanced(Field::list('Hidden fields', 'Names of values to pass in the link, e.g. utm_source.', 'utm_source')),
                'settings' => Field::advanced(Field::json('Settings (JSON)', 'Typeform settings, e.g. {"language": "en", "is_public": true}.')),
                'definition' => Field::advanced(Field::json('Full definition (JSON)', 'Anything else the Create Form API takes (welcome_screens, thankyou_screens, logic, theme). Merged with the settings above.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $title = $this->requiredString($config, 'title', 'The title');
        $fields = array_values((array) ($this->decoded($config['fields'] ?? null, 'The questions') ?? []));

        foreach ($fields as $field) {
            if (! is_array($field) || ! filled($field['type'] ?? null) || ! filled($field['title'] ?? null)) {
                throw new InvalidArgumentException('Every question needs a type and a title.');
            }
        }

        $hidden = array_values(array_filter(array_map('trim', is_array($config['hidden'] ?? null) ? $config['hidden'] : explode(',', (string) ($config['hidden'] ?? '')))));
        $body = [
            ...(array) ($this->decoded($config['definition'] ?? null, 'The full definition') ?? []),
            'title' => $title,
            'fields' => $fields === [] ? null : $fields,
            'hidden' => $hidden === [] ? null : $hidden,
            'settings' => $this->decoded($config['settings'] ?? null, 'The settings'),
            'workspace' => filled($config['workspace_id'] ?? null) ? ['href' => $this->workspaceHref($run, $config, (string) $config['workspace_id'])] : null,
        ];

        return $this->shapeForm((array) $this->typeform($run, $config, 'POST', '/forms', array_filter($body, fn (mixed $value): bool => $value !== null)));
    }

    /**
     * Typeform names a workspace by its link, not its id.
     *
     * @param  array<string, mixed>  $config
     */
    private function workspaceHref(Run $run, array $config, string $id): string
    {
        return $this->host($run, $config).'/workspaces/'.rawurlencode($id);
    }
}
