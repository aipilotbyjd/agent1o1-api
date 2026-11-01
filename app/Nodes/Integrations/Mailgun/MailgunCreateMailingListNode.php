<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailgunCreateMailingListNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_create_mailing_list';
    }

    public function name(): string
    {
        return 'Mailgun: Create Mailing List';
    }

    public function description(): string
    {
        return 'Creates a mailing list with its own address (mail sent to the address goes to every member).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['address'],
            'properties' => [
                ...$this->credentialFields(),
                'address' => Field::text('List address', null, 'news@mg.example.com'),
                'name' => Field::text('Name'),
                'description' => Field::textarea('Description'),
                'access_level' => Field::advanced(Field::select('Who can post', ['readonly' => 'Only you', 'members' => 'Members', 'everyone' => 'Anyone'], null, 'readonly')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $address = $this->email($config, 'address');
        $level = in_array($config['access_level'] ?? 'readonly', ['readonly', 'members', 'everyone'], true) ? ($config['access_level'] ?? 'readonly') : 'readonly';

        $created = (array) $this->mailgun($run, $config, 'POST', '/v3/lists', ['address' => $address, 'name' => filled($config['name'] ?? null) ? $this->oneLine($config['name']) : null, 'description' => filled($config['description'] ?? null) ? trim((string) $config['description']) : null, 'access_level' => $level]);

        return ['address' => data_get($created, 'list.address', $address), 'name' => data_get($created, 'list.name'), 'access_level' => data_get($created, 'list.access_level', $level)];
    }
}
