<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Changelog;

use App\Enums\Changelog\ChangelogCategory;
use App\Enums\Changelog\ChangelogVisibility;
use App\Http\Requests\Api\SuperAdmin\V1\Broadcasts\AudienceRules;
use App\Models\Changelog\ChangelogEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A changelog entry create (POST) or partial update (PATCH). The cover image
 * may only be an https URL, and the slug is fixed once an entry exists so
 * permalinks never break.
 */
class ChangelogEntryRequest extends FormRequest
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
        $presence = $this->route('entry') instanceof ChangelogEntry ? 'sometimes' : 'required';

        return [
            'title' => [$presence, 'string', 'max:160'],
            'slug' => [Rule::prohibitedIf($this->route('entry') instanceof ChangelogEntry), 'nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('changelog_entries', 'slug')],
            'version' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\-+_]*$/'],
            'summary' => [$presence, 'string', 'max:500'],
            'body' => [$presence, 'string', 'max:50000'],
            'cover_image_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:#^https://[^\s]+$#'],
            'category' => ['sometimes', Rule::enum(ChangelogCategory::class)],
            'areas' => ['sometimes', 'nullable', 'array', 'max:10'],
            'areas.*' => ['string', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'visibility' => ['sometimes', Rule::enum(ChangelogVisibility::class)],
            'is_pinned' => ['sometimes', 'boolean'],
            'is_major' => ['sometimes', 'boolean'],
            ...AudienceRules::for(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cover_image_url.regex' => 'The cover image must be an https:// link.',
            'slug.regex' => 'The slug can use lowercase letters, numbers and single hyphens.',
            'areas.*.regex' => 'Areas are lowercase words joined by hyphens, like "workflows" or "api-keys".',
        ];
    }
}
