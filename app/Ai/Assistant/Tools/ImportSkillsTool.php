<?php

namespace App\Ai\Assistant\Tools;

use App\Authorization\WorkspaceContext;
use App\Enums\Assistant\AssistantToolEffect;
use App\Enums\Workspaces\Permission;
use App\Models\Assistant\Assistant;
use App\Services\Agents\Skills\SkillSources;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

/**
 * Clones skills from a GitHub repository into the owner's workspace skill
 * library, kept in sync with the repository: the same synced source the
 * Skills page's "Sync from GitHub" makes. Only for an owner who may manage
 * skills.
 */
class ImportSkillsTool extends AssistantTool
{
    public const string NAME = 'import_skills_from_github';

    public function __construct(
        private readonly Assistant $assistant,
        private readonly SkillSources $sources,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function effect(): AssistantToolEffect
    {
        return AssistantToolEffect::Write;
    }

    public function description(): Stringable|string
    {
        return 'Clones skills from a GitHub repository (every folder with a SKILL.md) into the workspace skill library, '
            .'where the person\'s agents can use them, and keeps them synced with the repository. '
            .'Use it when the person shares a GitHub link to skills or asks to install skills from a repository.';
    }

    public function approvalReason(Request $request): string
    {
        return 'Import skills from GitHub: '.($request['repo'] ?? 'a repository');
    }

    protected function execute(Request $request): string
    {
        $owner = $this->assistant->user;

        if (! WorkspaceContext::resolveRole($this->assistant->workspace, $owner)?->has(Permission::AgentSkillManage)) {
            return 'Could not import: you are not allowed to add skills in this workspace.';
        }

        try {
            $source = $this->sources->importNow($this->assistant->workspace, $owner, [
                'repo' => (string) ($request['repo'] ?? ''),
                'branch' => $request['branch'] ?? null,
                'path' => $request['path'] ?? null,
            ]);
        } catch (ValidationException $e) {
            return 'Could not import: '.collect($e->errors())->flatten()->implode(' ');
        } catch (RuntimeException $e) {
            return 'Could not import: '.$e->getMessage();
        }

        $names = $source->skills()->pluck('name');

        return $names->isEmpty()
            ? "Synced {$source->repo}, but it has no skills (folders with a SKILL.md)".($source->path ? " under {$source->path}" : '').'.'
            : "Imported {$names->count()} skill(s) from {$source->repo}: {$names->implode(', ')}. They stay in sync with the repository and can be attached to agents from the Skills page.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'repo' => $schema->string()->description('The repository as owner/name, or any GitHub link to it: a /tree/{branch}/{folder} link also picks the branch and folder.')->required(),
            'branch' => $schema->string()->description('Branch to sync from. Defaults to the repository\'s default branch.'),
            'path' => $schema->string()->description('Folder inside the repository that holds the skills. Defaults to the whole repository.'),
        ];
    }
}
