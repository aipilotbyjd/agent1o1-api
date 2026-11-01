<?php

namespace App\Nodes\Integrations\Resend;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class ResendListContactsNode extends AbstractResendNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'resend_list_contacts';
    }

    public function name(): string
    {
        return 'Resend: List Contacts';
    }

    public function description(): string
    {
        return 'Lists the contacts of an audience, newest first. As a trigger it starts the flow for each new contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['audience_id'],
            'properties' => [
                ...$this->credentialFields(),
                'audience_id' => $this->audienceField(),
                'created_after' => Field::advanced(Field::datetime('Added after')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = filled($config['created_after'] ?? null) ? Carbon::parse((string) $config['created_after'])->utc() : null;
        $contacts = $this->listAll($run, $config, '/audiences/'.$this->pathPart($config, 'audience_id', 'The audience').'/contacts', [], $this->limit($config, 50));

        usort($contacts, fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        if ($since !== null) {
            $contacts = array_values(array_filter($contacts, fn (array $contact): bool => isset($contact['created_at']) && Carbon::parse($contact['created_at'])->greaterThanOrEqualTo($since)));
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
        return 'Resend: New contact';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a contact is added to an audience. Instant, with a check every few minutes.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'created_after' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'limit' => 1000];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
