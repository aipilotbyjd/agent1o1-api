<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\GatesActions;
use App\Authorization\WorkspaceContext;
use App\Enums\Agents\ActionEffect;
use App\Enums\Workspaces\Permission;
use App\Models\Agents\Agent;
use App\Models\Agents\Skill;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lets an agent with `allow_skill_editing` save a repeatable process as a new
 * workspace skill and attach it to itself, so later conversations can load it
 * through `UseSkillTool`. The skill is created as the person chatting (or the
 * agent's creator for a run nobody started), and only if that person may
 * manage skills: the same permission the Skills page requires.
 */
class CreateSkillTool implements Approvable, Tool
{
    use GatesActions;

    public const NAME = 'create_skill';

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
        return 'Saves a new skill: a reusable playbook you can load in future conversations with `'.UseSkillTool::NAME.'`. '
            .'Use it when the user asks you to remember how to do a task, or teaches you a multi-step process, template or format worth reusing. '
            .'Do not use it for one-off facts; those belong in memory.';
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
     * Changing the agent itself is a write like any other: in Ask mode it
     * waits for a person, and a read-only agent can't do it at all.
     *
     * @param  array<string, mixed>  $effectiveArguments
     */
    protected function actionEffect(array $effectiveArguments): ActionEffect
    {
        return ActionEffect::Write;
    }

    private function perform(Request $request): Stringable|string
    {
        $name = trim((string) $request['name']);
        $instructions = trim((string) $request['instructions']);

        if ($name === '' || $instructions === '') {
            return 'Not created: a skill needs a name and instructions.';
        }

        if (mb_strlen($instructions) > Skill::INSTRUCTIONS_MAX_LENGTH) {
            return 'Not created: the instructions are too long. Shorten them and try again.';
        }

        $workspace = $this->agent->workspace;
        $user = User::query()->find($this->userId ?? $this->agent->created_by);

        if ($user === null || ! WorkspaceContext::resolveRole($workspace, $user)?->has(Permission::AgentSkillManage)) {
            return 'Not created: the person you are working for is not allowed to create skills in this workspace.';
        }

        if ($workspace->skills()->whereRaw('lower(name) = ?', [Str::lower($name)])->exists()) {
            return "Not created: a skill named \"{$name}\" already exists. Use `".UpdateSkillTool::NAME.'` to change a skill you have, or pick another name.';
        }

        $skill = $workspace->skills()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'description' => Str::limit(trim((string) ($request['description'] ?? '')), Skill::DESCRIPTION_MAX_LENGTH, '') ?: null,
            'instructions' => $instructions,
            'created_by' => $user->id,
        ]);

        Agent::query()->findOrFail($this->agent->id)->skills()->attach($skill->id);

        return "Created the skill \"{$skill->name}\" and attached it to you. It is available from your next reply.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('A short, specific name, e.g. "Weekly Sales Report".')->required(),
            'description' => $schema->string()->description('What the skill does and when to load it. This is all you will see of it until you load it.')->required(),
            'instructions' => $schema->string()->description('The full playbook: steps, rules, templates and formats to follow.')->required(),
        ];
    }
}
