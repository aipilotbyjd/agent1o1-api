<?php

namespace App\Nodes\Integrations\DropboxSign;

use App\Contracts\SupportsTriggerMode;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class DropboxSignListRequestsNode extends AbstractDropboxSignNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'dropbox_sign_list_requests';
    }

    public function name(): string
    {
        return 'Dropbox Sign: List Signature Requests';
    }

    public function description(): string
    {
        return 'Lists documents sent for signature, newest first. As a trigger it starts the flow for each document that is fully signed.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'which' => Field::select('Show', ['complete' => 'Fully signed', 'pending' => 'Waiting for signatures', 'all' => 'Everything'], null, 'complete'),
                'search' => Field::advanced(Field::text('Search', 'Dropbox Sign search words, e.g. subject:contract or to:ada@example.com.')),
                'limit' => Field::integer('Most requests', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = trim(match ($config['which'] ?? 'complete') {
            'complete' => 'complete:true',
            'pending' => 'complete:false',
            default => '',
        }.' '.trim((string) ($config['search'] ?? '')));

        $body = $this->call($run, $config, 'GET', self::API_URL.'/signature_request/list', query: [
            'page_size' => max(1, min(100, (int) ($config['limit'] ?? 25))),
            'query' => $query !== '' ? $query : null,
        ]);

        $requests = array_map(fn (mixed $request): array => $this->shapeRequest((array) $request), (array) ($body['signature_requests'] ?? []));

        return ['requests' => $requests, 'count' => count($requests)];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'requests' => ['title' => 'Requests', 'description' => 'Each: signature_request_id, status, title, subject, created_at and signers.'],
                'count' => ['title' => 'Count', 'description' => 'How many were returned.'],
            ],
        ];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'requests';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['signature_request_id'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Dropbox Sign: Document signed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a document sent through Dropbox Sign has been signed by everyone.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
