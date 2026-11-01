<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class MailchimpListMembersNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_list_members';
    }

    public function name(): string
    {
        return 'Mailchimp: List Members';
    }

    public function description(): string
    {
        return 'Lists the people on an audience, optionally by status or changed since a time.';
    }

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
                'list_id' => $this->audienceField(),
                'status' => Field::select('Status', ['any' => 'Any', 'subscribed' => 'Subscribed', 'unsubscribed' => 'Unsubscribed', 'cleaned' => 'Cleaned (bounced)', 'pending' => 'Waiting to confirm', 'transactional' => 'Transactional', 'archived' => 'Archived'], null, 'any'),
                'changed_since' => Field::advanced(Field::datetime('Changed since', 'Only people whose details changed at or after this time.')),
                'tag' => Field::advanced(Field::text('Tag', 'Only people with this tag.')),
                'limit' => Field::integer('Max results', null, 50, 1, 10000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = (string) ($config['status'] ?? 'any');
        $members = $this->listAll($run, $config, '/lists/'.$this->listId($config).'/members', 'members', [
            'status' => $status !== 'any' && $status !== '' ? $status : null,
            'since_last_changed' => filled($config['changed_since'] ?? null) ? Carbon::parse((string) $config['changed_since'])->utc()->toIso8601String() : null,
            'sort_field' => 'last_changed',
            'sort_dir' => 'DESC',
        ], $this->limit($config, 50, 10000));

        if (filled($config['tag'] ?? null)) {
            $tag = mb_strtolower((string) $config['tag']);
            $members = array_values(array_filter($members, fn (array $member): bool => collect((array) ($member['tags'] ?? []))->contains(fn (mixed $item): bool => is_array($item) && mb_strtolower((string) ($item['name'] ?? '')) === $tag)));
        }

        return ['members' => array_map($this->shapeMember(...), $members), 'count' => count($members)];
    }
}
