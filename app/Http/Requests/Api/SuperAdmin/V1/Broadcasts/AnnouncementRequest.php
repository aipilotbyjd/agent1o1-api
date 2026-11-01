<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Broadcasts;

use App\Enums\Broadcasts\AnnouncementType;
use App\Enums\Notifications\AlertSeverity;
use App\Models\Broadcasts\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An announcement create (POST) or partial update (PATCH). The link may only
 * be an https URL or a path inside the app, so a banner can't carry
 * `javascript:` or any other scheme.
 */
class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $presence = $this->route('announcement') instanceof Announcement ? 'sometimes' : 'required';

        return [
            'type' => ['sometimes', Rule::enum(AnnouncementType::class)],
            'severity' => ['sometimes', Rule::enum(AlertSeverity::class)],
            'title' => [$presence, 'string', 'max:160'],
            'body' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'cta_label' => ['nullable', 'string', 'max:60', 'required_with:cta_url'],
            'cta_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:#^(https://[^\s]+|/(?!/)[^\s]*)$#'],
            'dismissible' => ['sometimes', 'boolean'],
            'blocks_writes' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            ...AudienceRules::for(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['cta_url.regex' => 'The link must start with https:// or be a path inside the app, like /billing.'];
    }
}
