<?php

namespace App\Nodes\Integrations\DocuSign;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DocuSignGetEnvelopeNode extends AbstractDocuSignNode
{
    public function type(): string
    {
        return 'docusign_get_envelope';
    }

    public function name(): string
    {
        return 'DocuSign: Get Envelope';
    }

    public function description(): string
    {
        return 'Checks where a document sent for signature stands: its status and, for each signer, whether they have signed.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['envelope_id'],
            'properties' => [
                ...$this->accountFields(),
                'envelope_id' => Field::text('Envelope ID', 'From a send step or List Envelopes.', 'a1b2c3d4-0000-0000-0000-000000000000'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->envelopeId($config);
        $envelope = $this->shapeEnvelope($this->call($run, $config, 'GET', $this->root($run, $config)."/envelopes/{$id}", query: ['include' => 'recipients']));

        return [...$envelope, 'result' => $envelope['status'], 'is_complete' => $envelope['status'] === 'completed'];
    }

    public function outputSchema(): array
    {
        $schema = parent::outputSchema();
        $schema['properties']['result'] = ['title' => 'Branch', 'description' => 'The status, so an edge can follow completed, declined or voided.'];
        $schema['properties']['is_complete'] = ['title' => 'Is complete', 'description' => 'True once every signer has signed.'];
        $schema['properties']['completed_at'] = ['title' => 'Completed at', 'description' => 'When the last signer signed.'];

        return $schema;
    }
}
