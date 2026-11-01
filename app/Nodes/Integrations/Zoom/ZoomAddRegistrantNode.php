<?php

namespace App\Nodes\Integrations\Zoom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZoomAddRegistrantNode extends AbstractZoomNode
{
    public function type(): string
    {
        return 'zoom_add_registrant';
    }

    public function name(): string
    {
        return 'Zoom: Add Registrant';
    }

    public function description(): string
    {
        return 'Registers a person for a meeting that needs registration and returns their own join link.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['meeting_id', 'email', 'first_name'],
            'properties' => [
                ...$this->credentialFields(),
                'meeting_id' => Field::text('Meeting ID', null, '85012345678'),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'first_name' => Field::text('First name', null, 'Ada'),
                'last_name' => Field::text('Last name'),
                'company' => Field::advanced(Field::text('Company')),
                'auto_approve' => Field::advanced(Field::boolean('Approve straight away', 'Skips the host’s approval step.', true)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->meetingId($config);
        $email = strtolower(trim((string) ($config['email'] ?? '')));
        $first = trim((string) ($config['first_name'] ?? ''));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("“{$email}” is not an email address.");
        }

        if ($first === '') {
            throw new InvalidArgumentException('The registrant needs a first name.');
        }

        $registrant = $this->call($run, $config, 'POST', self::API_URL."/meetings/{$id}/registrants", array_filter([
            'email' => $email,
            'first_name' => mb_substr($first, 0, 64),
            'last_name' => filled($config['last_name'] ?? null) ? mb_substr(trim((string) $config['last_name']), 0, 64) : null,
            'org' => filled($config['company'] ?? null) ? mb_substr(trim((string) $config['company']), 0, 200) : null,
            'auto_approve' => (bool) ($config['auto_approve'] ?? true),
        ], fn (mixed $value): bool => $value !== null));

        return ['registrant_id' => $registrant['registrant_id'] ?? null, 'join_url' => $registrant['join_url'] ?? null, 'meeting_id' => $id, 'email' => $email];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'registrant_id' => ['title' => 'Registrant ID', 'description' => 'Zoom’s ID for the registration.'],
                'join_url' => ['title' => 'Their join link', 'description' => 'A link only this person should use. Send it to them.'],
                'meeting_id' => ['title' => 'Meeting ID', 'description' => 'The meeting.'],
                'email' => ['title' => 'Email', 'description' => 'The address registered.'],
            ],
        ];
    }
}
