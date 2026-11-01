<?php

namespace App\Nodes\Integrations\GoogleDocs;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Str;

class GoogleDocsReadTextNode extends AbstractGoogleDocsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int MAX_CHARS = 200_000;

    public function type(): string
    {
        return 'google_docs_read_text';
    }

    public function name(): string
    {
        return 'Google Docs: Read Text';
    }

    public function description(): string
    {
        return 'Reads a document as plain text, including the text in its tables.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['document_id'],
            'properties' => [
                ...$this->credentialFields(),
                'document_id' => Field::dynamic('Document', 'google_docs.documents', 'Pick a document, or enter its ID from the URL.', '1AbCdEfGhIjKlMnOp'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $document = $this->get($run, '/documents/'.rawurlencode((string) $config['document_id']), $config);
        $text = $this->textOf((array) ($document['body']['content'] ?? []));

        return [
            'document_id' => $document['documentId'] ?? $config['document_id'],
            'title' => $document['title'] ?? null,
            'revision_id' => $document['revisionId'] ?? null,
            'text' => Str::limit($text, self::MAX_CHARS, ''),
            'truncated' => mb_strlen($text) > self::MAX_CHARS,
        ];
    }

    /**
     * Where the item list sits in execute()'s output: the output is the one
     * item, so there is no list.
     */
    protected function triggerItemsPath(): ?string
    {
        return null;
    }

    /**
     * The document itself, as one item, once it has a revision.
     */
    public function triggerItems(array $output, array $config): array
    {
        return isset($output['revision_id']) && $output['revision_id'] !== '' ? [$output] : [];
    }

    /**
     * Each revision of the document is a new item.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['revision_id']) ? ($item['document_id'] ?? '').':'.$item['revision_id'] : '';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Docs: Document updated';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a document is edited, with its new text. Use "Group events" to wait for a pause in typing.';
    }

    /**
     * Docs saves as people type: a poll without push every ten minutes keeps
     * the cost low, and a push event brings it forward.
     */
    public function triggerPollMinutes(): int
    {
        return 10;
    }

    /**
     * The text of a document body's elements: paragraphs, then table cells
     * row by row (cells tab-separated), nested tables included.
     *
     * @param  list<array<string, mixed>>  $elements
     */
    private function textOf(array $elements): string
    {
        $text = '';

        foreach ($elements as $element) {
            if (isset($element['paragraph'])) {
                foreach ((array) ($element['paragraph']['elements'] ?? []) as $part) {
                    $text .= $part['textRun']['content'] ?? '';
                }
            } elseif (isset($element['table'])) {
                foreach ((array) ($element['table']['tableRows'] ?? []) as $row) {
                    $cells = array_map(
                        fn (array $cell): string => trim($this->textOf((array) ($cell['content'] ?? []))),
                        (array) ($row['tableCells'] ?? []),
                    );

                    $text .= implode("\t", $cells)."\n";
                }
            }
        }

        return $text;
    }
}
