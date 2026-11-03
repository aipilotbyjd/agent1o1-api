<?php

namespace App\Ai\Tools\Workspace;

use App\Enums\Agents\ActionEffect;
use App\Enums\Agents\ActionToolKind;
use App\Enums\Agents\ActionVerdict;
use App\Enums\Agents\AgentActionStatus;
use App\Models\Agents\AgentAction;
use App\Services\Agents\Approvals\ActionContext;
use App\Services\Agents\Approvals\ActionMessages;
use App\Services\Agents\UserQuestions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lets an agent stop and ask the person it's chatting with a question,
 * shown in the chat as buttons, a text box or a small form, instead of
 * guessing or writing a long clarifying paragraph.
 *
 * It rides on the approvals machinery rather than duplicating it: asking
 * records an `AgentAction` of kind `Question` and pauses the turn exactly
 * like an action waiting for approval (`AgentRunner::pauseTurn()`).
 * Answering is a decision (`answer`, see `ResolveAgentActionsAction`) that
 * stores the answer on the action, and resuming runs this tool, which hands
 * the answer back to the model as the call's result. Skipping the question
 * is a rejection, which the model is told about like any other.
 *
 * Questions never go through `ActionGate`: they change nothing, so no mode,
 * rule or guardrail applies, and they are only offered where a person is
 * watching a conversation that can pause (`ToolRegistry`).
 */
class AskUserTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public const string NAME = 'ask_user';

    public function __construct(
        private readonly ActionContext $context,
        private readonly UserQuestions $questions,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Asks the user a question and waits for their answer before you continue. '
            .'Use it when you cannot proceed well without information only they have, or must choose between options where a wrong guess would be costly. '
            .'Prefer `options` (shown as buttons) when there are a few likely answers, set `multiple` to let them pick several, '
            .'and use `fields` for a small form when you need several values at once (e.g. a name, a date and an amount). '
            .'Ask one clear question at a time and do not ask about things you can look up yourself or that the user already told you. '
            .'Do not repeat the question in your reply text: it is shown to the user as its own card.';
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $action = $this->questionFor($request);

        return Approval::required($action->arguments['question'] ?? 'The agent has a question.');
    }

    public function handle(Request $request): Stringable|string
    {
        $action = $this->existing($request->toolCallId());

        if ($action === null) {
            return json_encode(['status' => 'waiting_for_answer', 'note' => 'The question is waiting for the user to answer.']) ?: '{}';
        }

        if ($action->status === AgentActionStatus::Approved) {
            return $this->recordAnswer($action);
        }

        return match ($action->status) {
            AgentActionStatus::Executed => (string) $action->result,
            AgentActionStatus::Pending, AgentActionStatus::Running => json_encode(['status' => 'waiting_for_answer', 'note' => 'The question is waiting for the user to answer.']) ?: '{}',
            default => ActionMessages::rejected($action),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()->description('The question, in plain words, addressed to the user.')->required(),
            'options' => $schema->array()->items($schema->string())->description('Up to '.UserQuestions::MAX_OPTIONS.' short answers to offer as buttons.'),
            'multiple' => $schema->boolean()->description('With options: let the user pick more than one.'),
            'allow_other' => $schema->boolean()->description('With options: also let the user type their own answer. Defaults to true.'),
            'fields' => $schema->array()->items($schema->object([
                'name' => $schema->string()->description('A short snake_case key for the value, e.g. "start_date".')->required(),
                'label' => $schema->string()->description('The label shown next to the input.')->required(),
                'type' => $schema->string()->enum(UserQuestions::FIELD_TYPES)->description('Defaults to text.'),
                'options' => $schema->array()->items($schema->string())->description('For a select field: the choices.'),
                'required' => $schema->boolean(),
                'placeholder' => $schema->string(),
            ]))->description('For a small form: up to '.UserQuestions::MAX_FIELDS.' inputs to fill in. Leave out for a single question.'),
        ];
    }

    /**
     * The question this call asked, recorded the first time the SDK weighs
     * the call. When a paused turn resumes the SDK weighs it again and gets
     * the same record back.
     */
    private function questionFor(Request $request): AgentAction
    {
        $toolCallId = $request->toolCallId();

        if ($toolCallId !== null && ($existing = $this->existing($toolCallId)) !== null) {
            return $existing;
        }

        $question = $this->questions->normalize($request->all());

        $action = new AgentAction([
            'workspace_id' => $this->context->agent->workspace_id,
            'agent_id' => $this->context->agent->id,
            'agent_session_id' => $this->context->session?->id,
            'run_id' => $this->context->run->id,
            'tool_call_id' => $toolCallId,
            'tool_name' => self::NAME,
            'tool_kind' => ActionToolKind::Question,
            'effect' => ActionEffect::Read,
            'arguments' => $question,
            'outcome' => ActionVerdict::Ask,
            'reason' => ['source' => 'question', 'detail' => $question['question']],
            'requested_at' => now(),
            'expires_at' => now()->addMinutes($this->context->policy->approval_ttl_minutes),
        ]);

        $action->forceFill(['status' => AgentActionStatus::Pending])->save();

        return $action;
    }

    private function existing(?string $toolCallId): ?AgentAction
    {
        if ($toolCallId === null) {
            return null;
        }

        return AgentAction::query()
            ->where('run_id', $this->context->run->id)
            ->where('agent_session_id', $this->context->session?->id)
            ->where('tool_call_id', $toolCallId)
            ->where('tool_kind', ActionToolKind::Question)
            ->latest('id')
            ->first();
    }

    /**
     * Hands an answered question's answer back, once: the answer was stored
     * with the decision, and the call is claimed before it's written up so
     * two resumes racing on the same turn agree on one result.
     */
    private function recordAnswer(AgentAction $action): string
    {
        $claimed = AgentAction::query()
            ->whereKey($action->id)
            ->where('status', AgentActionStatus::Approved)
            ->update(['status' => AgentActionStatus::Running, 'updated_at' => now()]);

        if ($claimed === 0) {
            return (string) ($action->fresh()?->result ?? '');
        }

        $result = $this->questions->resultText($action->effectiveArguments()['answer'] ?? '');

        $action->forceFill([
            'status' => AgentActionStatus::Executed,
            'result' => $result,
            'executed_at' => now(),
        ])->save();

        return $result;
    }
}
