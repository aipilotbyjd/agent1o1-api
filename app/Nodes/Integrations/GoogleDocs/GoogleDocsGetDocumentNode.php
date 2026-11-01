<?php

namespace App\Nodes\Integrations\GoogleDocs;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleDocsGetDocumentNode extends AbstractGoogleDocsNode
{
    public function type(): string
    {
        return 'google_docs_get_document';
    }

    public function name(): string
    {
        return 'Google Docs: Get Document';
    }

    public function description(): string
    {
        return 'Fetches the contents of a Google Doc.';
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
        return $this->get($run, "/documents/{$config['document_id']}", $config);
    }
}
