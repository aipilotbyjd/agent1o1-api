<?php

namespace App\Http\Requests\Api\Internal\V1\Assistant;

use App\Enums\Assistant\AssistantInboxLabelGroup;
use App\Models\Assistant\AssistantInboxLabel;
use App\Models\Workspaces\Workspace;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveInboxLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Gmail rejects some characters in label names; "/" would nest the label.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:60', 'not_regex:/[\/\\\\"]/', $this->uniqueLabelName()],
            'definition' => [$creating ? 'required' : 'sometimes', 'string', 'max:500'],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'group' => ['sometimes', Rule::enum(AssistantInboxLabelGroup::class)],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Label names are unique per owner, ignoring case: Gmail treats "Invoices"
     * and "invoices" as the same label. The label being edited is not a clash.
     */
    private function uniqueLabelName(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $workspace = $this->route('workspace');
            $label = $this->route('label');

            $exists = AssistantInboxLabel::query()
                ->whereHas('config.assistant', fn ($query) => $query
                    ->where('workspace_id', $workspace instanceof Workspace ? $workspace->id : $workspace)
                    ->where('user_id', $this->user()?->id))
                ->whereRaw('lower(name) = ?', [Str::lower((string) $value)])
                ->when($label instanceof AssistantInboxLabel, fn ($query) => $query->whereKeyNot($label->id))
                ->exists();

            if ($exists) {
                $fail('You already have a label with that name.');
            }
        };
    }
}
