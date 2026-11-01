<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Enums\Workflows\PageAccess;
use App\Enums\Workflows\PageBlockType;
use App\Services\Workflows\Pages\PageSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Every field is optional, so the builder's autosave can send just the
 * content and the share settings can send just the access. The content's
 * structure is checked here; its props are put into shape by
 * `PageSchema::normalize()`.
 */
class UpdateWorkflowPageRequest extends FormRequest
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
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'workflow_id' => ['sometimes', 'required', 'uuid'],
            'content' => ['sometimes', 'required', 'array'],
            'content.blocks' => ['present_with:content', 'array', 'max:'.PageSchema::MAX_BLOCKS],
            'content.blocks.*' => ['array'],
            'content.blocks.*.id' => ['required', 'string', 'max:64'],
            'content.blocks.*.type' => ['required', Rule::enum(PageBlockType::class)],
            'content.blocks.*.width' => ['nullable', Rule::in(PageSchema::WIDTHS)],
            'content.blocks.*.props' => ['nullable', 'array'],
            'content.blocks.*.children' => ['nullable', 'array'],
            'content.theme' => ['nullable', 'array'],
            'variant_content' => ['sometimes', 'required', 'array'],
            'variant_content.blocks' => ['present_with:variant_content', 'array', 'max:'.PageSchema::MAX_BLOCKS],
            'variant_content.theme' => ['nullable', 'array'],
            'variant_split' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'base_revision' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'access' => ['sometimes', 'required', Rule::enum(PageAccess::class)],
            'password' => ['sometimes', 'nullable', 'string', 'min:6', 'max:200'],
            'is_enabled' => ['sometimes', 'boolean'],
            'max_runs_per_day' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'share_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'share_description' => ['sometimes', 'nullable', 'string', 'max:300'],
            'share_image_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'url:http,https'],
        ];
    }
}
