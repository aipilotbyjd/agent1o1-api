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
 * Lets an agent with `allow_skill_editing` improve one of its attached skills
 * when the user corrects how it did a task that skill covers. Only skills
 * attached to this agent can be changed, and only by someone who may manage
 * skills. A change to the instructions bumps the skill's version, the same as
 * an edit on the Skills page.
 *
 * A skill attached to other agents too is left alone: a correction made in
 * one agent's chat would otherwise silently change how every other agent
 * using it behaves. That's an edit for the Skills page, where its reach is
 * visible.
 */
class UpdateSkillTool implements Approvable, Tool
{
    use GatesActions;

    public const NAME = 'update_skill';

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
        return 'Updates one of your skills so you do the task right next time. '
            .'Use it when the user corrects how you did something a skill covers. '
            .'Load the skill first, then pass its COMPLETE revised instructions: they replace the current ones, so keep everything that still applies.';
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
        $name = trim((string) $request['skill']);
        $skill = Agent::query()->findOrFail($this->agent->id)->skills
            ->first(fn (Skill $skill): bool => strcasecmp($skill->name, $name) === 0);

        if ($skill === null) {
            return "Not updated: you have no skill named \"{$name}\".";
        }

        if ($skill->isSynced()) {
            return "Not updated: \"{$skill->name}\" is synced from a GitHub repository, which would undo your change on its next sync. "
                .'Tell the user what to change so they can update it in the repository.';
        }

        $user = User::query()->find($this->userId ?? $this->agent->created_by);

        if ($user === null || ! WorkspaceContext::resolveRole($this->agent->workspace, $user)?->has(Permission::AgentSkillManage)) {
            return 'Not updated: the person you are working for is not allowed to edit skills in this workspace.';
        }

        if ($skill->agents()->whereKeyNot($this->agent->id)->exists()) {
            return "Not updated: \"{$skill->name}\" is shared with other agents, so changing it here would change them too. "
                .'Tell the user to edit it on the Skills page instead.';
        }

        $changes = array_filter([
            'description' => trim((string) ($request['description'] ?? '')),
            'instructions' => trim((string) ($request['instructions'] ?? '')),
        ], filled(...));

        if ($changes === []) {
            return 'Not updated: pass new instructions or a new description.';
        }

        if (isset($changes['description'])) {
            $changes['description'] = Str::limit($changes['description'], Skill::DESCRIPTION_MAX_LENGTH, '');
        }

        if (mb_strlen($changes['instructions'] ?? '') > Skill::INSTRUCTIONS_MAX_LENGTH) {
            return 'Not updated: the instructions are too long. Shorten them and try again.';
        }

        // `version` isn't fillable, so it's set alongside the edit in one save.
        if (array_key_exists('instructions', $changes) && $changes['instructions'] !== $skill->instructions) {
            $changes['version'] = $skill->version + 1;
        }

        $skill->forceFill($changes)->save();

        return "Updated the skill \"{$skill->name}\".";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'skill' => $schema->string()
                ->enum($this->agent->skills->pluck('name')->values()->all())
                ->description('The exact name of the skill to update.')
                ->required(),
            'instructions' => $schema->string()->description('The complete revised instructions.'),
            'description' => $schema->string()->description('A revised description, only if what the skill covers has changed.'),
        ];
    }
}
