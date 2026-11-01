<?php

namespace App\Nodes\Integrations\GoogleDocs;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleDocsAppendTextNode extends AbstractGoogleDocsNode
{
    public function type(): string
    {
        return 'google_docs_append_text';
    }

    public function name(): string
    {
        return 'Google Docs: Append Text';
    }

    public function description(): string
    {
        return 'Appends text to the end of a Google Doc.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['document_id', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                'document_id' => Field::dynamic('Document', 'google_docs.documents', 'Pick a document, or enter its ID from the URL.', '1AbCdEfGhIjKlMnOp'),
                'text' => Field::textarea('Text', 'Added to the end of the document.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, "/documents/{$config['document_id']}:batchUpdate", $config, [
            'requests' => [
                [
                    'insertText' => [
                        'endOfSegmentLocation' => ['segmentId' => ''],
                        'text' => $config['text'],
                    ],
                ],
            ],
        ]);
    }
}
