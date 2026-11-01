<?php

namespace App\Nodes\Integrations\GoogleForms;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GoogleFormsDeleteItemNode extends AbstractGoogleFormsNode
{
    public function type(): string
    {
        return 'google_forms_delete_item';
    }

    public function name(): string
    {
        return 'Google Forms: Delete Question';
    }

    public function description(): string
    {
        return 'Removes a question (or section) from a form, found by its title, item id or question id. Answers already given to it stay in the responses.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['form_id', 'item'],
            'properties' => [
                ...$this->credentialFields(),
                'form_id' => $this->formField(),
                'item' => Field::text('Question', 'Its exact title, item id or question id.', 'What did you like most?'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $wanted = trim((string) ($config['item'] ?? ''));

        if ($wanted === '') {
            throw new InvalidArgumentException('The question to delete is required.');
        }

        $items = array_values((array) ($this->fetchForm($run, $config)['items'] ?? []));
        $matches = array_keys(array_filter($items, fn (array $item): bool => in_array($wanted, [(string) ($item['itemId'] ?? ''), (string) ($item['title'] ?? ''), (string) ($item['questionItem']['question']['questionId'] ?? '')], true)));

        if ($matches === []) {
            throw new InvalidArgumentException("No question matches \"{$wanted}\" in this form.");
        }

        if (count($matches) > 1) {
            throw new InvalidArgumentException("More than one question matches \"{$wanted}\": use its item id.");
        }

        $this->post($run, $this->formPath($config).':batchUpdate', $config, ['requests' => [['deleteItem' => ['location' => ['index' => $matches[0]]]]]]);

        return ['deleted' => true, 'form_id' => (string) $config['form_id'], 'title' => $items[$matches[0]]['title'] ?? null, 'position' => $matches[0]];
    }
}
