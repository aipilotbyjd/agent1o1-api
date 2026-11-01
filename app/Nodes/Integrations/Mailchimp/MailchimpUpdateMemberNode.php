<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MailchimpUpdateMemberNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_update_member';
    }

    public function name(): string
    {
        return 'Mailchimp: Update Member';
    }

    public function description(): string
    {
        return 'Changes details of a person already on an audience (name, merge fields, VIP, language). Fails if they are not on it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->audienceField(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'new_email' => Field::advanced(Field::text('New email address', 'Change their address.')),

                'first_name' => Field::text('First name'),
                'last_name' => Field::text('Last name'),
                'phone' => Field::advanced(Field::text('Phone')),
                'merge_fields' => Field::advanced(Field::json('Other merge fields (JSON)', 'Your own audience fields by tag, e.g. {"COMPANY": "Acme", "BIRTHDAY": "03/15"}.')),

                'vip' => Field::advanced(Field::select('VIP', ['unchanged' => 'Leave as it is', 'yes' => 'Yes', 'no' => 'No'], null, 'unchanged')),
                'language' => Field::advanced(Field::text('Language', null, 'en')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $vip = (string) ($config['vip'] ?? 'unchanged');
        $merge = $this->mergeFields($config);

        $body = [
            'email_address' => filled($config['new_email'] ?? null) ? $this->email($config, 'new_email') : null,
            'merge_fields' => $merge === [] ? null : $merge,
            'vip' => $vip === 'yes' ? true : ($vip === 'no' ? false : null),
            'language' => filled($config['language'] ?? null) ? (string) $config['language'] : null,
        ];

        if (array_filter($body, fn (mixed $value): bool => $value !== null) === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        return $this->shapeMember((array) $this->mailchimp($run, $config, 'PATCH', $this->memberPath($config), $body));
    }
}
