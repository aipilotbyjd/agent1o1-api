<?php

namespace App\Nodes\Integrations\GoogleForms;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GoogleFormsListResponsesNode extends AbstractGoogleFormsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'google_forms_list_responses';
    }

    public function name(): string
    {
        return 'Google Forms: List Responses';
    }

    public function description(): string
    {
        return 'Lists a form\'s responses, with every answer next to its question.';
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
                'submitted_after' => Field::datetime('Submitted after', 'Only responses submitted after this time.'),
                'max_results' => Field::integer('Max results', null, 50, 1, 5000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $after = trim((string) ($config['submitted_after'] ?? ''));

        $responses = $this->get($run, $this->formPath($config).'/responses', $config, [
            'pageSize' => $config['max_results'] ?? 50,
            'filter' => $after !== '' ? 'timestamp > '.$after : null,
        ]);

        // The form is read only to name the questions: a poll with nothing new skips it.
        $list = array_values(array_filter((array) ($responses['responses'] ?? []), 'is_array'));
        $titles = $list === [] ? [] : $this->questionTitles($this->fetchForm($run, $config));

        return [
            'responses' => array_map(fn (array $response): array => $this->response($response, $titles), $list),
        ];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'responses';
    }

    /**
     * A response's id is its stable id.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['response_id'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Forms: New response';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when someone submits the form.';
    }

    /**
     * Read the responses of the last day, not all of them: a form with years
     * of answers would be read in full on every poll. A response already
     * seen is never run twice, so the window only has to outlast any gap
     * between polls.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'max_results' => 500, 'submitted_after' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z')];
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }
}
