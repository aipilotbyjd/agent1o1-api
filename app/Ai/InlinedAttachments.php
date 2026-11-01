<?php

namespace App\Ai;

use App\Models\Artifacts\Artifact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;

/**
 * A chat message's files, shaped for a provider that only accepts images as
 * attachments (the OpenAI-compatible gateway refuses documents): text files
 * are written into the message itself, images ride along as attachments.
 */
final class InlinedAttachments
{
    /**
     * @param  Collection<int, Artifact>  $artifacts
     * @return array{0: string, 1: array<int, Image|Document>} The message text with any text files appended, and the remaining attachments.
     */
    public static function split(string $content, Collection $artifacts): array
    {
        $textMimeTypes = config('artifacts.message_attachments.text_mime_types');
        [$texts, $files] = $artifacts->partition(fn (Artifact $artifact): bool => in_array($artifact->mime_type, $textMimeTypes, true));

        $inlined = $texts->map(fn (Artifact $artifact): string => sprintf(
            "<attached_file name=\"%s\">\n%s\n</attached_file>",
            $artifact->filename,
            (string) Storage::disk($artifact->disk)->get($artifact->path),
        ));

        return [
            collect([$content, ...$inlined])->implode("\n\n"),
            $files->map(fn (Artifact $artifact) => $artifact->toPromptAttachment())->values()->all(),
        ];
    }
}
