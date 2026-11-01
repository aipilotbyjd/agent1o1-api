<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage disk
    |--------------------------------------------------------------------------
    |
    | Where artifact bytes live: both agent-exported files
    | (App\Ai\Tools\ExportArtifactTool) and member uploads. Point this at an
    | S3-compatible disk in production; `Artifact.disk` records the disk each
    | row was written to, so changing this never orphans existing files.
    */

    'disk' => env('ARTIFACTS_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Upload limit
    |--------------------------------------------------------------------------
    |
    | Maximum size of a single uploaded artifact, in kilobytes. PHP's own
    | `upload_max_filesize`/`post_max_size` still apply and must be at least
    | this large, or the request never reaches validation.
    */

    'max_upload_kilobytes' => (int) env('ARTIFACTS_MAX_UPLOAD_KILOBYTES', 25600),

    /*
    |--------------------------------------------------------------------------
    | Chat message attachments
    |--------------------------------------------------------------------------
    |
    | Files a member can attach to a single agent chat message. Each is stored
    | as an artifact and sent to the model with that message. Mime types are
    | detected from the file's bytes, and limited to what the model providers
    | accept as input: images, PDFs, and plain-text formats.
    */

    'message_attachments' => [
        'max_files' => (int) env('ARTIFACTS_MESSAGE_ATTACHMENTS_MAX_FILES', 10),

        'image_mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],

        'document_mime_types' => ['application/pdf'],

        'text_mime_types' => [
            'text/plain', 'text/markdown', 'text/csv', 'text/html', 'text/xml',
            'application/json', 'application/xml', 'text/yaml', 'application/yaml',
        ],
    ],

];
