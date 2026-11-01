<?php

namespace App\Http\Requests\Concerns;

use Closure;
use Illuminate\Http\UploadedFile;

/**
 * Files sent with a chat message as multipart `attachments[]`: shared by
 * every chat that hands its member's files to the model, so they all accept
 * the same types and sizes (`config('artifacts.message_attachments')`).
 */
trait ValidatesMessageAttachments
{
    /**
     * `$documents: false` refuses PDFs, for a chat whose model is only ever
     * sent images and inlined text (see `App\Ai\InlinedAttachments`).
     *
     * @return array<string, mixed>
     */
    protected function attachmentRules(bool $documents = true): array
    {
        return [
            'attachments' => ['nullable', 'array', 'max:'.config('artifacts.message_attachments.max_files')],
            'attachments.*' => [
                'file',
                'max:'.config('artifacts.max_upload_kilobytes'),
                function (string $attribute, mixed $value, Closure $fail) use ($documents): void {
                    if (! $value instanceof UploadedFile) {
                        return; // `file` above already rejected it.
                    }

                    // Detected from the file's own bytes, not the client's
                    // Content-Type header: the same value the artifact is
                    // stored and later sent to the provider as.
                    $config = config('artifacts.message_attachments');
                    $allowed = [...$config['image_mime_types'], ...($documents ? $config['document_mime_types'] : []), ...$config['text_mime_types']];

                    if (! in_array($value->getMimeType(), $allowed, true)) {
                        $fail($documents
                            ? 'The :attribute must be an image (JPEG, PNG, GIF, WebP), a PDF, or a plain-text file.'
                            : 'The :attribute must be an image (JPEG, PNG, GIF, WebP) or a plain-text file.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function attachmentFiles(): array
    {
        return $this->file('attachments', []);
    }
}
