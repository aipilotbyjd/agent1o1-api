<?php

return [

    /*
    |--------------------------------------------------------------------------
    | File ingestion
    |--------------------------------------------------------------------------
    |
    | `POST .../knowledge-base` accepts a `file` instead of raw `text` for
    | plain-text-like formats. There is no PDF/Office text-extraction
    | library in this project (CLAUDE.md: no new dependencies without
    | approval), so only formats readable as UTF-8 text directly are
    | supported: Gumloop's PDF/.docx/.pptx/.xlsx file sources are not.
    */

    'max_upload_kilobytes' => (int) env('KNOWLEDGE_BASE_MAX_UPLOAD_KILOBYTES', 5120),

    /*
    |--------------------------------------------------------------------------
    | Inline vs. queued ingestion
    |--------------------------------------------------------------------------
    |
    | Text up to this many characters is embedded during the request (and the
    | chunks returned); anything longer is queued as an `IngestKnowledgeJob`
    | and the request answers 202.
    */

    'sync_ingest_max_characters' => (int) env('KNOWLEDGE_BASE_SYNC_INGEST_MAX_CHARACTERS', 20000),

    /*
    |--------------------------------------------------------------------------
    | Always-included knowledge budget
    |--------------------------------------------------------------------------
    |
    | The most characters of the documents in an agent's always-included
    | collections that `SkillInjector` puts in its system prompt, summed
    | across documents. Documents past the budget are left out (and logged),
    | not truncated.
    */

    'max_injected_characters' => (int) env('KNOWLEDGE_BASE_MAX_INJECTED_CHARACTERS', 100000),

    'allowed_extensions' => ['txt', 'md', 'markdown', 'csv', 'json', 'xml', 'yaml', 'yml', 'html', 'htm'],

    /*
    |--------------------------------------------------------------------------
    | Synced sources
    |--------------------------------------------------------------------------
    |
    | Web pages and connected apps (Drive, Gmail, Outlook, GitHub, Slack)
    | the knowledge base keeps in sync: shared, or private to one member.
    */

    'sources' => [
        'max_per_workspace' => 200,

        // Documents read per sync; the rest come on the next one.
        'max_documents_per_sync' => 50,
        'max_document_chars' => 100000,

        'sync_every_minutes' => 60,
    ],

];
