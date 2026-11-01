<?php

namespace App\Nodes\Integrations\DocuSign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DocuSignVoidEnvelopeNode extends AbstractDocuSignNode
{
    public function type(): string
    {
        return 'docusign_void_envelope';
    }

    public function name(): string
    {
        return 'DocuSign: Void Envelope';
    }

    public function description(): string
    {
        return 'Cancels a document that has been sent but not fully signed, telling the signers why.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['envelope_id', 'reason'],
            'properties' => [
                ...$this->accountFields(),
                'envelope_id' => Field::text('Envelope ID', null, 'a1b2c3d4-0000-0000-0000-000000000000'),
                'reason' => Field::text('Reason', 'Shown to the signers.', 'The terms changed; a new agreement will follow.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->envelopeId($config);
        $reason = trim((string) ($config['reason'] ?? ''));

        if ($reason === '') {
            throw new InvalidArgumentException('Give a reason; the signers are shown it.');
        }

        $this->call($run, $config, 'PUT', $this->root($run, $config)."/envelopes/{$id}", ['status' => 'voided', 'voidedReason' => mb_substr($reason, 0, 200)]);

        return ['envelope_id' => $id, 'status' => 'voided', 'voided' => true];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'envelope_id' => ['title' => 'Envelope ID', 'description' => 'The envelope cancelled.'],
                'status' => ['title' => 'Status', 'description' => 'voided.'],
                'voided' => ['title' => 'Voided', 'description' => 'True when it was cancelled.'],
            ],
        ];
    }
}
