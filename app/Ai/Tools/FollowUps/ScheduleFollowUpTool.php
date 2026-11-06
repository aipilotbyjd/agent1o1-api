<?php

namespace App\Ai\Tools\FollowUps;

use App\Ai\Tools\Concerns\GatesActions;
use App\Enums\Agents\ActionEffect;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Services\Agents\AgentFollowUps;
use App\Services\Secrets\PlatformSecrets;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lets an agent come back to the conversation later on its own: "I'll check
 * this PR again tomorrow at 9", or "every Monday, summarise last week's
 * tickets". See `AgentFollowUps` for what gets created and how it runs.
 *
 * Scheduling is a write that sets the agent loose later with nobody
 * watching, so it goes through the agent's approvals like any other change:
 * in Ask mode the person approves the schedule first.
 */
class ScheduleFollowUpTool implements Approvable, Tool
{
    use GatesActions;

    public const string NAME = 'schedule_follow_up';

    public function __construct(
        private readonly Agent $agent,
        private readonly AgentSession $session,
        private readonly AgentFollowUps $followUps,
        private readonly ?string $userId = null,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Schedules you to come back to this conversation later and do a task on your own, e.g. "check whether the PR was merged" tomorrow at 9:00, '
            .'or "summarise last week\'s tickets" every Monday. When it runs you get the task as a new message in this conversation, with everything said so far, '
            .'and your reply appears here for the user. '
            .'Use run_at for one time (ISO 8601 date and time; one without an offset is read in the user\'s timezone) or cron for a repeating schedule '
            .'("0 9 * * 1-5" = 9:00 on weekdays), never both. '
            .'Write the task as an instruction to yourself with every detail you will need, since you may not remember why it matters: '
            .'name the PR, ticket, URL or person. Use it when the user asks you to check back, remind them, or keep an eye on something, '
            .'or when you offer to and they agree. Tell the user when it will run.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->guarded($request, fn (array $arguments): string => $this->schedule($arguments));
    }

    /**
     * @return array<string, mixed>
     */
    protected function actionArguments(Request $request): array
    {
        return array_filter([
            'task' => $request['task'] ?? null,
            'title' => $request['title'] ?? null,
            'run_at' => $request['run_at'] ?? null,
            'cron' => $request['cron'] ?? null,
            'timezone' => $request['timezone'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
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

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function schedule(array $arguments): string
    {
        try {
            $trigger = $this->followUps->schedule($this->agent, $this->session, $this->userId, $arguments);
        } catch (ValidationException $e) {
            return json_encode([
                'error' => 'The follow-up was not scheduled: '.collect($e->errors())->flatten()->implode(' '),
                'note' => 'Fix the arguments and try again, or tell the user why it could not be scheduled.',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        } catch (PlanLimitExceededException $e) {
            return json_encode([
                'error' => 'The follow-up was not scheduled: '.app(PlatformSecrets::class)->redact($e->getMessage()),
                'note' => 'Tell the user their plan has no room for another active trigger.',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        return json_encode([
            'scheduled' => true,
            ...$this->followUps->describe($trigger),
            'note' => 'Tell the user when it will run. They can see, pause or delete it under this agent\'s triggers, or ask you to cancel it.',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'task' => $schema->string()->description('What to do when it runs, as a complete instruction to yourself.')->required(),
            'title' => $schema->string()->description('A short name for the follow-up, shown to the user.'),
            'run_at' => $schema->string()->description('For a one-time follow-up: the ISO 8601 date and time, e.g. "2026-05-04T09:00".'),
            'cron' => $schema->string()->description('For a repeating follow-up: a 5-field cron expression.'),
            'timezone' => $schema->string()->description('An IANA timezone, only if the user named one. Defaults to the user\'s own.'),
        ];
    }
}
