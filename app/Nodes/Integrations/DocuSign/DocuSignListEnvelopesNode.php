<?php

namespace App\Nodes\Integrations\DocuSign;

use App\Contracts\SupportsTriggerMode;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class DocuSignListEnvelopesNode extends AbstractDocuSignNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_DAYS = 30;

    public function type(): string
    {
        return 'docusign_list_envelopes';
    }

    public function name(): string
    {
        return 'DocuSign: List Envelopes';
    }

    public function description(): string
    {
        return 'Lists documents sent for signature, newest first, by status and date. As a trigger it starts the flow when one is signed.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->accountFields(),
                'status' => Field::select('Status', ['any' => 'Any', 'completed' => 'Completed (all signed)', 'sent' => 'Sent', 'delivered' => 'Delivered', 'declined' => 'Declined', 'voided' => 'Voided', 'created' => 'Draft'], null, 'completed'),
                'since' => Field::datetime('Changed since', 'Leave empty for the last 30 days.'),
                'search' => Field::advanced(Field::text('Search', 'Words in the subject or a signer’s name or email.')),
                'limit' => Field::integer('Most envelopes', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = (string) ($config['status'] ?? 'completed');
        $since = filled($config['since'] ?? null) ? $config['since'] : now()->subDays(self::TRIGGER_WINDOW_DAYS)->toIso8601String();

        $body = $this->call($run, $config, 'GET', $this->root($run, $config).'/envelopes', query: [
            'from_date' => $since,
            'status' => $status !== 'any' ? $status : null,
            'search_text' => filled($config['search'] ?? null) ? trim((string) $config['search']) : null,
            'count' => max(1, min(100, (int) ($config['limit'] ?? 25))),
            'order' => 'desc',
            'order_by' => 'last_modified',
            'include' => 'recipients',
        ]);

        $envelopes = array_map(fn (mixed $envelope): array => $this->shapeEnvelope((array) $envelope), (array) ($body['envelopes'] ?? []));

        return ['envelopes' => $envelopes, 'count' => count($envelopes)];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'envelopes' => ['title' => 'Envelopes', 'description' => 'Each: envelope_id, status, subject, sent_at, completed_at and signers.'],
                'count' => ['title' => 'Count', 'description' => 'How many were returned.'],
            ],
        ];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'envelopes';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['envelope_id'] ?? '');
    }

    public function triggerName(): string
    {
        return 'DocuSign: Document signed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a document sent through DocuSign has been signed by everyone (or reaches the status you choose).';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'since' => now()->subDays(self::TRIGGER_WINDOW_DAYS)->toIso8601String(), 'limit' => 100];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
