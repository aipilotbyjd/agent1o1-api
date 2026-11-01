<?php

namespace App\Nodes\Integrations\Kit;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class KitListFormSubscribersNode extends AbstractKitNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'kit_list_form_subscribers';
    }

    public function name(): string
    {
        return 'Kit: List Form Subscribers';
    }

    public function description(): string
    {
        return 'Lists the people who signed up through a form, newest first. As a trigger it starts the flow for each new signup on that form.';
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
                'form_id' => Field::dynamic('Form', 'kit.forms', 'Pick a form.', 'Newsletter signup', type: 'integer'),
                'added_after' => Field::advanced(Field::datetime('Signed up after')),
                'limit' => Field::integer('Max results', null, 50, 1, 10000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $form = $this->numericId($config, 'form_id', 'The form');
        $subscribers = $this->listAll($run, $config, "/forms/{$form}/subscribers", 'subscribers', [
            'added_after' => filled($config['added_after'] ?? null) ? Carbon::parse((string) $config['added_after'])->utc()->toIso8601String() : null,
            'status' => 'all',
            'sort_order' => 'desc',
        ], $this->limit($config, 50, 10000));

        return ['subscribers' => array_map(fn (array $subscriber): array => [...$this->shapeSubscriber($subscriber), 'added_at' => $subscriber['added_at'] ?? null, 'form_id' => $form], $subscribers), 'count' => count($subscribers)];
    }

    private const int TRIGGER_WINDOW_MINUTES = 360;

    protected function triggerItemsPath(): ?string
    {
        return 'subscribers';
    }

    /**
     * The person and the moment: someone can sign up through the same form again.
     */
    public function triggerItemId(array $item): string
    {
        return ($item['id'] ?? '').':'.($item['added_at'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Kit: Form submitted';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone signs up through a Kit form.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'added_after' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'limit' => 1000];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
