<?php

namespace App\Http\Requests\Api\Internal\V1\Agents\Concerns;

use App\Models\Agents\Skill;
use App\Models\Agents\SkillReference;
use App\Models\Workspaces\Workspace;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Rules shared by the store and update skill requests. Names and slugs are
 * unique per workspace: the agent looks a skill up by name, so two skills
 * with the same name could never both be loaded, and the `skills` table's
 * unique `(workspace_id, slug)` index would otherwise turn a clash into a 500.
 */
trait ValidatesSkillFields
{
    /**
     * @return array<string, mixed>
     */
    protected function skillFieldRules(?Skill $ignore = null): array
    {
        return [
            'name' => ['string', 'max:255', $this->uniqueSkillName($ignore)],
            // Soft-deleted skills still hold their slug in the unique index, so
            // this deliberately does not ignore trashed rows.
            'slug' => ['string', 'max:255', 'alpha_dash', Rule::unique('skills', 'slug')
                ->where('workspace_id', $this->workspaceId())
                ->ignore($ignore?->id)],
            'description' => ['nullable', 'string', 'max:'.Skill::DESCRIPTION_MAX_LENGTH],
            'category' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:64'],
            'instructions' => ['string', 'max:'.Skill::INSTRUCTIONS_MAX_LENGTH],
            'is_shared' => ['boolean'],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function referenceContentRules(): array
    {
        return ['string', 'max:'.SkillReference::CONTENT_MAX_LENGTH];
    }

    private function uniqueSkillName(?Skill $ignore): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
            $exists = Skill::query()
                ->where('workspace_id', $this->workspaceId())
                ->whereRaw('lower(name) = ?', [Str::lower((string) $value)])
                ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->id))
                ->exists();

            if ($exists) {
                $fail('A skill with this name already exists in the workspace.');
            }
        };
    }

    private function workspaceId(): ?string
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace ? $workspace->id : $workspace;
    }
}
