<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class MailchimpAddOrUpdateMemberNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_add_or_update_member';
    }

    public function name(): string
    {
        return 'Mailchimp: Add or Update Member';
    }

    public function description(): string
    {
        return 'Adds a person to an audience, or updates them if they are already there. Someone who unsubscribed can\'t be added back this way: Mailchimp requires them to resubscribe themselves.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
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
                'status_if_new' => Field::select('If they are new', ['subscribed' => 'Subscribe them', 'pending' => 'Send a confirmation email first', 'unsubscribed' => 'Add as unsubscribed', 'transactional' => 'Transactional only'], 'How a person who is not on the audience yet is added.', 'subscribed'),
                'status' => Field::select('Change status', ['unchanged' => 'Leave as it is', 'subscribed' => 'Subscribe', 'unsubscribed' => 'Unsubscribe', 'pending' => 'Ask to confirm'], 'For someone already on the audience.', 'unchanged'),

                'first_name' => Field::text('First name'),
                'last_name' => Field::text('Last name'),
                'phone' => Field::advanced(Field::text('Phone')),
                'merge_fields' => Field::advanced(Field::json('Other merge fields (JSON)', 'Your own audience fields by tag, e.g. {"COMPANY": "Acme", "BIRTHDAY": "03/15"}.')),

                'tags' => Field::list('Tags', 'Tags to put on them.', 'customer'),
                'vip' => Field::advanced(Field::select('VIP', ['unchanged' => 'Leave as it is', 'yes' => 'Yes', 'no' => 'No'], null, 'unchanged')),
                'language' => Field::advanced(Field::text('Language', 'A language code such as en or fr.', 'en')),
                'email_type' => Field::advanced(Field::select('Email format', ['unchanged' => 'Leave as it is', 'html' => 'HTML', 'text' => 'Plain text'], null, 'unchanged')),
                'signed_up_at' => Field::advanced(Field::datetime('Signed up at', 'When they signed up, if not now.')),
                'ip_signup' => Field::advanced(Field::text('Signup IP address')),
                'interests' => Field::advanced(Field::json('Interests (JSON)', 'Group ids set true or false, e.g. {"a1b2c3": true}.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $new = (string) ($config['status_if_new'] ?? 'subscribed');
        $status = (string) ($config['status'] ?? 'unchanged');
        $vip = (string) ($config['vip'] ?? 'unchanged');
        $type = (string) ($config['email_type'] ?? 'unchanged');
        $merge = $this->mergeFields($config);

        if (! in_array($new, ['subscribed', 'pending', 'unsubscribed', 'transactional'], true)) {
            throw new InvalidArgumentException("\"{$new}\" is not a status for a new member.");
        }

        $member = (array) $this->mailchimp($run, $config, 'PUT', $this->memberPath($config), [
            'email_address' => $this->email($config),
            'status_if_new' => $new,
            'status' => in_array($status, ['subscribed', 'unsubscribed', 'pending'], true) ? $status : null,
            'merge_fields' => $merge === [] ? null : $merge,
            'tags' => $this->csv($config['tags'] ?? null) ?: null,
            'vip' => $vip === 'yes' ? true : ($vip === 'no' ? false : null),
            'language' => filled($config['language'] ?? null) ? (string) $config['language'] : null,
            'email_type' => in_array($type, ['html', 'text'], true) ? $type : null,
            'timestamp_signup' => filled($config['signed_up_at'] ?? null) ? Carbon::parse((string) $config['signed_up_at'])->utc()->format('Y-m-d H:i:s') : null,
            'ip_signup' => filled($config['ip_signup'] ?? null) ? (string) $config['ip_signup'] : null,
            'interests' => $this->decoded($config['interests'] ?? null, 'The interests'),
        ]);

        return $this->shapeMember($member);
    }
}
