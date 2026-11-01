<?php

namespace App\Nodes\Integrations\Zoom;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZoomListRegistrantsNode extends AbstractZoomNode
{
    public function type(): string
    {
        return 'zoom_list_registrants';
    }

    public function name(): string
    {
        return 'Zoom: List Registrants';
    }

    public function description(): string
    {
        return 'Lists who has registered for a meeting, by whether they are approved, pending or denied.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['meeting_id'],
            'properties' => [
                ...$this->credentialFields(),
                'meeting_id' => Field::text('Meeting ID', null, '85012345678'),
                'status' => Field::select('Status', ['approved' => 'Approved', 'pending' => 'Waiting for approval', 'denied' => 'Denied'], null, 'approved'),
                'limit' => Field::integer('Most people', null, 100, 1, 300),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = in_array($config['status'] ?? 'approved', ['approved', 'pending', 'denied'], true) ? $config['status'] ?? 'approved' : 'approved';

        $body = $this->call($run, $config, 'GET', self::API_URL.'/meetings/'.$this->meetingId($config).'/registrants', query: ['status' => $status, 'page_size' => max(1, min(300, (int) ($config['limit'] ?? 100)))]);

        $people = array_map(fn (mixed $person): array => [
            'registrant_id' => $person['id'] ?? null,
            'email' => $person['email'] ?? null,
            'first_name' => $person['first_name'] ?? null,
            'last_name' => $person['last_name'] ?? null,
            'company' => $person['org'] ?? null,
            'status' => $person['status'] ?? $status,
            'registered_at' => $person['create_time'] ?? null,
            'join_url' => $person['join_url'] ?? null,
        ], (array) ($body['registrants'] ?? []));

        return ['registrants' => $people, 'count' => count($people)];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'registrants' => ['title' => 'Registrants', 'description' => 'Each: registrant_id, email, first_name, last_name, company, status, registered_at and join_url.'],
                'count' => ['title' => 'Count', 'description' => 'How many were returned.'],
            ],
        ];
    }
}
