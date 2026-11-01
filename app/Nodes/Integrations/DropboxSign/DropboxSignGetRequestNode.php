<?php

namespace App\Nodes\Integrations\DropboxSign;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DropboxSignGetRequestNode extends AbstractDropboxSignNode
{
    public function type(): string
    {
        return 'dropbox_sign_get_request';
    }

    public function name(): string
    {
        return 'Dropbox Sign: Get Signature Request';
    }

    public function description(): string
    {
        return 'Checks where a document sent for signature stands: its status and whether each signer has signed.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['signature_request_id'],
            'properties' => [
                ...$this->credentialFields(),
                'signature_request_id' => Field::text('Request ID', 'From a send step or List Signature Requests.', 'fa5c8a0b0f492d768749333ad6fcc214c111e967'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->requestId($config);
        $request = $this->shapeRequest((array) ($this->call($run, $config, 'GET', self::API_URL."/signature_request/{$id}")['signature_request'] ?? []));

        return [...$request, 'result' => $request['status']];
    }

    public function outputSchema(): array
    {
        $schema = parent::outputSchema();
        $schema['properties']['result'] = ['title' => 'Branch', 'description' => 'The status, so an edge can follow complete, declined or awaiting_signature.'];

        return $schema;
    }
}
