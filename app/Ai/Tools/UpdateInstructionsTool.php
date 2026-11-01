<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\GatesActions;
use App\Authorization\WorkspaceContext;
use App\Enums\Agents\ActionEffect;
use App\Enums\Workspaces\Permission;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\User;
use App\Services\Agents\SkillInjector;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lets an agent with `allow_self_updates` rewrite its own instructions when
 * the user corrects it or states a lasting preference. Saving goes through
 * `AgentObserver`, so every change is a new `AgentVersion` that can be rolled
 * back. The agent is reloaded rather than saved from the turn's copy, which
 * is a pinned-version snapshot whose other attributes aren't the live ones.
 *
 * The model sees its full prompt, which also carries the skills, knowledge
 * and memories `SkillInjector` adds. Models copy those back despite being
 * told not to, often slightly reworded, so any injected section is stripped
 * before saving, and then any line that closely matches an injected line
 * without being in the current base instructions; otherwise it would be
 * duplicated, freeze details like today's date, and outlive the skill being
 * detached. Models asked for only the change instead tend not to call the
 * tool at all.
 *
 * Only when the person it's working for (or the agent's creator, for a run
 * nobody started) may manage agents, otherwise anyone who can chat with it
 * could permanently change it for the whole workspace, which the agent's
 * settings page wouldn't let them do.
 *
 * The conversation the correction was made in is moved onto the new
 * version, so the change applies from the agent's next reply there rather
 * than only in later conversations.
 */
class UpdateInstructionsTool implements Approvable, Tool
{
    use GatesActions;

    private const INJECTED_LINE_SIMILARITY = 80;

    private ?Agent $liveAgent = null;

    public function __construct(
        private readonly Agent $agent,
        private readonly SkillInjector $skillInjector,
        private readonly ?string $userId = null,
        private readonly ?AgentSession $session = null,
    ) {}

    public function name(): string
    {
        return 'update_own_instructions';
    }

    public function description(): Stringable|string
    {
        return 'Permanently updates your own base instructions. Use it only when the user corrects how you behave or sets a rule '
            .'for how you should work in all future conversations (e.g. "always answer in Spanish", "end every answer with a question"), not for one-off requests. '
            .'Do not use it for facts about the user or their work, such as their name or what to call them; save those with `'.RememberTool::NAME.'`. '
            .'Pass the COMPLETE revised base instructions: they replace the current ones, so keep everything that still applies. '
            .'Do not include the "About you", "## Skills", "# Knowledge" or memory sections; those are managed separately. '
            ."Your current base instructions are:\n\n".($this->liveAgent()->instructions ?? '(none yet)');
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
        $agent = $this->liveAgent();

        $user = User::query()->find($this->userId ?? $agent->created_by);

        if ($user === null || ! WorkspaceContext::resolveRole($agent->workspace, $user)?->has(Permission::AgentManage)) {
            return 'Not updated: the person you are working for is not allowed to change this agent. '
                .'Tell them to ask someone who manages agents to edit your instructions.';
        }

        $instructions = $this->withoutInjectedText((string) $request['instructions'], $agent);

        if ($instructions === '') {
            return 'Not updated: the instructions were empty.';
        }

        $agent->update(['instructions' => $instructions]);

        $this->session?->update(['agent_version_id' => $agent->versions()->latest('version')->value('id')]);

        return 'Instructions updated. The change applies from your next reply.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'instructions' => $schema->string()->description('The complete revised base instructions.')->required(),
        ];
    }

    private function withoutInjectedText(string $instructions, Agent $agent): string
    {
        $sections = $this->skillInjector->injectedSections($agent, $this->userId);
        $baseLines = $this->lines((string) $agent->instructions);
        $injectedLines = array_values(array_diff($this->lines(implode("\n", $sections)), $baseLines));

        $kept = array_filter(
            explode("\n", str_replace($sections, '', $instructions)),
            fn (string $line): bool => in_array(trim($line), $baseLines, true)
                || ! $this->closelyMatchesAny(trim($line), $injectedLines),
        );

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $kept)));
    }

    /**
     * @param  array<int, string>  $candidates
     */
    private function closelyMatchesAny(string $line, array $candidates): bool
    {
        if ($line === '') {
            return false;
        }

        foreach ($candidates as $candidate) {
            similar_text($line, $candidate, $percent);

            if ($percent >= self::INJECTED_LINE_SIMILARITY) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function lines(string $text): array
    {
        return array_values(array_filter(array_map(trim(...), explode("\n", $text)), filled(...)));
    }

    private function liveAgent(): Agent
    {
        return $this->liveAgent ??= Agent::query()->findOrFail($this->agent->id);
    }
}
