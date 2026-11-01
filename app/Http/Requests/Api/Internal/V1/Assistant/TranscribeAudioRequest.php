<?php

namespace App\Http\Requests\Api\Internal\V1\Assistant;

use Illuminate\Foundation\Http\FormRequest;

class TranscribeAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Browsers record voice as webm/ogg (Chrome, Firefox) or mp4 (Safari).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'audio' => [
                'required',
                'file',
                'mimetypes:audio/webm,video/webm,audio/ogg,audio/mpeg,audio/mp4,video/mp4,audio/x-m4a,audio/wav,audio/x-wav',
                'max:'.config('assistant.transcription.max_kilobytes'),
            ],
        ];
    }
}
