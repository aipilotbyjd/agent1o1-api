<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Facades\Http;

class ResendAddContactNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_add_contact';
    }

    public function name(): string
    {
        return 'Resend: Add or Update Contact';
    }

    public function description(): string
    {
        return 'Adds a contact to an audience, or updates them if their email is already there. Safe to run twice.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['audience_id', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'audience_id' => $this->audienceField(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'first_name' => Field::text('First name'),
                'last_name' => Field::text('Last name'),
                'unsubscribed' => Field::advanced(Field::select('Subscription', ['unchanged' => 'Leave as it is', 'subscribed' => 'Subscribed', 'unsubscribed' => 'Unsubscribed'], null, 'unchanged')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $audience = $this->pathPart($config, 'audience_id', 'The audience');
        $email = $this->email($config);
        $state = (string) ($config['unsubscribed'] ?? 'unchanged');
        $fields = [
            'first_name' => filled($config['first_name'] ?? null) ? (string) $config['first_name'] : null,
            'last_name' => filled($config['last_name'] ?? null) ? (string) $config['last_name'] : null,
            'unsubscribed' => $state === 'unchanged' ? null : $state === 'unsubscribed',
        ];

        $existing = Http::withToken($this->resolveAccessToken($run, $config))->acceptJson()->timeout(30)->get('https://api.resend.com/audiences/'.$audience.'/contacts/'.rawurlencode($email));

        if ($existing->status() !== 404) {
            $this->assertOk($existing);
            $this->resend($run, $config, 'PATCH', '/audiences/'.$audience.'/contacts/'.rawurlencode($email), $fields);

            return ['id' => $existing->json('id'), 'email' => $email, 'created' => false];
        }

        $created = (array) $this->resend($run, $config, 'POST', '/audiences/'.$audience.'/contacts', ['email' => $email, ...$fields]);

        return ['id' => $created['id'] ?? null, 'email' => $email, 'created' => true];
    }
}
