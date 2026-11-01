<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\GatesActions;
use App\Authorization\WorkspaceContext;
use App\Enums\Agents\ActionEffect;
use App\Enums\Workspaces\Permission;
use App\Models\Agents\Agent;
use App\Models\Agents\Skill;
use App\Models\User;
use App\Services\Agents\Skills\SkillSources;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

/**
 * Lets an agent with `allow_skill_editing` clone skills from a GitHub
 * repository: the repository is connected as a synced skill source (the same
 * one the Skills page's "Sync from GitHub" makes) and its skills are attached
 * to the agent. Runs as the person chatting, who must be able to manage skills.
 */
class ImportSkillsTool implements Approvable, Tool
{
    use GatesActions;

    public const NAME = 'import_skills_from_github';

    public function __construct(
        private readonly Agent $agent,
        private readonly ?string $userId = null,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Clones skills from a GitHub repository (every folder with a SKILL.md) into the workspace, keeps them synced with the repository, '
            .'and attaches them to you so you can load them with `'.UseSkillTool::NAME.'`. '
            .'Use it when the user shares a GitHub link to skills or asks you to install skills from a repository.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->guarded($request, fn (array $arguments): string => (string) $this->perform(new Request($arguments, $request->toolCallId())));
    }

    /**
     * @return array<string, mixed>
     */
    protected function actionArguments(Request $request): array
    {
        return $request->all();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function effectiveArguments(array $arguments): array
    {
        return $arguments;
    }

    /**
     * @param  array<string, mixed>  $effectiveArguments
     */
    protected function actionEffect(array $effectiveArguments): ActionEffect
    {
        return ActionEffect::Write;
    }

    private function perform(Request $request): Stringable|string
    {
        $workspace = $this->agent->workspace;
        $user = User::query()->find($this->userId ?? $this->agent->created_by);

        if ($user === null || ! WorkspaceContext::resolveRole($workspace, $user)?->has(Permission::AgentSkillManage)) {
            return 'Not imported: the person you are working for is not allowed to add skills in this workspace.';
        }

        try {
            $source = app(SkillSources::class)->importNow($workspace, $user, [
                'repo' => (string) ($request['repo'] ?? ''),
                'branch' => $request['branch'] ?? null,
                'path' => $request['path'] ?? null,
            ]);
        } catch (ValidationException $e) {
            return 'Not imported: '.collect($e->errors())->flatten()->implode(' ');
        } catch (RuntimeException $e) {
            return 'Not imported: '.$e->getMessage();
        }

        $wanted = collect($request['skills'] ?? [])->map(fn ($name): string => Str::lower(trim((string) $name)))->filter();
        $skills = $source->skills()->get()
            ->when($wanted->isNotEmpty(), fn ($skills) => $skills->filter(fn (Skill $skill): bool => $wanted->contains(Str::lower($skill->name))));

        if ($skills->isEmpty()) {
            return $wanted->isNotEmpty()
                ? "Synced {$source->repo}, but none of its skills are named ".$wanted->implode(', ').'. It has: '.$source->skills()->pluck('name')->implode(', ').'.'
                : "Synced {$source->repo}, but it has no skills (folders with a SKILL.md)".($source->path ? " under {$source->path}" : '').'.';
        }

        Agent::query()->findOrFail($this->agent->id)->skills()->syncWithoutDetaching($skills->modelKeys());

        return "Imported from {$source->repo} and attached to you: ".$skills->pluck('name')->implode(', ')
            .'. They stay in sync with the repository and are available from your next reply.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'repo' => $schema->string()->description('The repository as owner/name, or any GitHub link to it: a /tree/{branch}/{folder} link also picks the branch and folder.')->required(),
            'branch' => $schema->string()->description('Branch to sync from. Defaults to the repository\'s default branch.'),
            'path' => $schema->string()->description('Folder inside the repository that holds the skills. Defaults to the whole repository.'),
            'skills' => $schema->array()->items($schema->string())->description('Names of the skills to attach to you. Leave out to attach every skill in the repository.'),
        ];
    }
}
