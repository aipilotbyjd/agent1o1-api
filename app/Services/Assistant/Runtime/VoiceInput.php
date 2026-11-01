<?php

namespace App\Services\Assistant\Runtime;

use App\Services\Ai\ModelCatalogResolver;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Transcription;

/**
 * Speech-to-text for the chat box, on the operator's configured provider.
 */
class VoiceInput
{
    public function isAvailable(): bool
    {
        return ModelCatalogResolver::providerIsConfigured((string) config('assistant.transcription.provider'));
    }

    public function transcribe(UploadedFile $audio): string
    {
        $transcript = Transcription::fromUpload($audio)->generate(
            (string) config('assistant.transcription.provider'),
            config('assistant.transcription.model'),
        );

        return trim((string) $transcript);
    }
}
