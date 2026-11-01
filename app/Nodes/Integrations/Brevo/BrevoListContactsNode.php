<?php

namespace App\Nodes\Integrations\Brevo;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class BrevoListContactsNode extends AbstractBrevoNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'brevo_list_contacts';
    }

    public function name(): string
    {
        return 'Brevo: List Contacts';
    }

    public function description(): string
    {
        return 'Lists contacts, newest first, optionally of a list or changed since a time. As a trigger it starts the flow for each new contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => Field::advanced($this->listField('Only on list')),
                'modified_after' => Field::advanced(Field::datetime('Changed after')),
                'created_after' => Field::advanced(Field::datetime('Created after')),
                'limit' => Field::integer('Max results', null, 50, 1, 10000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = filled($config['created_after'] ?? null) ? Carbon::parse((string) $config['created_after'])->utc() : null;
        $contacts = $this->listAll($run, $config, is_numeric($config['list_id'] ?? null) ? '/contacts/lists/'.(int) $config['list_id'].'/contacts' : '/contacts', 'contacts', [
            'modifiedSince' => filled($config['modified_after'] ?? null) ? Carbon::parse((string) $config['modified_after'])->utc()->toIso8601String() : null,
            'sort' => 'desc',
        ], $this->limit($config, 50, 10000));

        // Brevo can't filter by creation time: the newest-first order lets a page be cut where it gets old.
        if ($since !== null) {
            $contacts = array_values(array_filter($contacts, fn (array $contact): bool => isset($contact['createdAt']) && Carbon::parse($contact['createdAt'])->greaterThanOrEqualTo($since)));
        }

        return ['contacts' => array_map($this->shapeContact(...), $contacts), 'count' => count($contacts)];
    }

    private const int TRIGGER_WINDOW_MINUTES = 360;

    protected function triggerItemsPath(): ?string
    {
        return 'contacts';
    }

    public function triggerName(): string
    {
        return 'Brevo: New contact';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact is added in Brevo. Checks every few minutes.';
    }

    /**
     * Those created in the look-back window.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'created_after' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'limit' => 500];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
