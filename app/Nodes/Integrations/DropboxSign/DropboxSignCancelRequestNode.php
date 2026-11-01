<?php

namespace App\Nodes\Integrations\DropboxSign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DropboxSignCancelRequestNode extends AbstractDropboxSignNode
{
    public function type(): string
    {
        return 'dropbox_sign_cancel_request';
    }

    public function name(): string
    {
        return 'Dropbox Sign: Cancel Request';
    }

    public function description(): string
    {
        return 'Cancels a document that has been sent but not fully signed. A completed request cannot be cancelled.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['signature_request_id'],
            'properties' => [
                ...$this->credentialFields(),
                'signature_request_id' => Field::text('Request ID', null, 'fa5c8a0b0f492d768749333ad6fcc214c111e967'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->requestId($config);
        $this->call($run, $config, 'POST', self::API_URL."/signature_request/cancel/{$id}", []);

        return ['signature_request_id' => $id, 'cancelled' => true];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'signature_request_id' => ['title' => 'Request ID', 'description' => 'The request cancelled.'],
                'cancelled' => ['title' => 'Cancelled', 'description' => 'True when it was cancelled.'],
            ],
        ];
    }
}
