<?php

namespace App\Nodes\Integrations\Typeform;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class TypeformListResponsesNode extends AbstractTypeformNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * How far back a poll looks for responses made since it last ran.
     */
    private const int TRIGGER_WINDOW_MINUTES = 360;

    public function type(): string
    {
        return 'typeform_list_responses';
    }

    public function name(): string
    {
        return 'Typeform: List Responses';
    }

    public function description(): string
    {
        return 'Lists a form\'s responses, newest first, each answer next to its question. As a trigger it starts the flow for each new submission, instantly when the form is named.';
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
                'status' => Field::select('Which', ['completed' => 'Submitted', 'partial' => 'Started but not submitted', 'all' => 'Both'], null, 'completed'),
                'since' => Field::advanced(Field::datetime('Submitted after', 'Only responses at or after this time.')),
                'until' => Field::advanced(Field::datetime('Submitted before', 'Only responses at or before this time.')),
                'query' => Field::advanced(Field::text('Contains', 'Only responses with this text in an answer.')),
                'limit' => Field::integer('Max results', null, 25, 1, 5000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $form = $this->formId($config);
        $status = (string) ($config['status'] ?? 'completed');

        $responses = $this->readResponses($run, $config, $form, [
            'completed' => $status === 'completed' ? 'true' : ($status === 'partial' ? 'false' : null),
            'since' => filled($config['since'] ?? null) ? Carbon::parse((string) $config['since'])->utc()->toIso8601String() : null,
            'until' => filled($config['until'] ?? null) ? Carbon::parse((string) $config['until'])->utc()->toIso8601String() : null,
            'query' => filled($config['query'] ?? null) ? (string) $config['query'] : null,
            'sort' => 'submitted_at,desc',
        ], $this->limit($config, 25, 5000));

        $questions = $responses === [] ? [] : $this->questions($run, $config, $form);

        return ['responses' => array_map(fn (array $response): array => [...$this->shapeResponse($response, $questions), 'form_id' => $form], $responses), 'count' => count($responses), 'form_id' => $form];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'responses';
    }

    /**
     * A response's token: it never changes and is unique to the response.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['token'] ?? $item['response_id'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Typeform: New response';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone submits a Typeform, with every answer next to its question.';
    }

    /**
     * Submitted responses of the last few hours, with a wide page.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'status' => $config['status'] ?? 'completed', 'since' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'limit' => 1000];
    }

    /**
     * A safety net: a named form is also pushed.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
