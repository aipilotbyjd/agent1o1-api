<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * A change to the people on an audience that starts a flow: someone subscribed,
 * unsubscribed, was cleaned, or had their details changed.
 *
 * Each kind reads the audience's members for the status it is about, newest
 * change first and only those since a look-back window, so a poll never reads
 * the whole audience. A webhook on the audience (`MailchimpSource`) makes the
 * poll look at once; the poll's own filters, order and dedupe decide what runs.
 */
abstract class AbstractMailchimpMemberTriggerNode extends AbstractMailchimpNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * How far back a poll looks for changes made since it last ran.
     */
    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * The member status this trigger is about, or null for any.
     */
    abstract protected function status(): ?string;

    /**
     * The member field changes are ordered and filtered by: `timestamp_opt` (when
     * they subscribed) or `last_changed`.
     */
    abstract protected function changedField(): string;

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->audienceField('The audience to watch.'),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only changes this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc()->toIso8601String();
        $status = $this->status();
        $subscribedField = $this->changedField() === 'timestamp_opt';

        $members = $this->listAll($run, $config, '/lists/'.$this->listId($config).'/members', 'members', [
            'status' => $status,
            'since_timestamp_opt' => $subscribedField ? $since : null,
            'since_last_changed' => $subscribedField ? null : $since,
            'sort_field' => $this->changedField(),
            'sort_dir' => 'DESC',
        ], $this->limit($config, 25));

        return ['members' => array_map($this->shapeMember(...), $members), 'count' => count($members)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'members';
    }

    /**
     * The person, and for a change the moment of it: someone can unsubscribe,
     * rejoin and unsubscribe again, and each is its own event.
     */
    public function triggerItemId(array $item): string
    {
        $id = (string) ($item['id'] ?? '');

        return $this->changedField() === 'timestamp_opt' ? $id : $id.':'.($item['last_changed'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'changed_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'limit' => 1000];
    }

    /**
     * A safety net: a webhook on the audience also makes it look at once.
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
