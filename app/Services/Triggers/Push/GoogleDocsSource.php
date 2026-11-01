<?php

namespace App\Services\Triggers\Push;

/**
 * "Document updated" in Google Docs, the moment the document changes.
 */
class GoogleDocsSource extends GoogleFileSource
{
    public function provider(): string
    {
        return 'google_docs';
    }

    protected function nodeTypes(): array
    {
        return ['google_docs_read_text'];
    }

    protected function fileIdField(): string
    {
        return 'document_id';
    }
}
