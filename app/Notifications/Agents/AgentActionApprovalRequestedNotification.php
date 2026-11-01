<?php

namespace App\Notifications\Agents;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentSession;
use App\Models\Agents\WorkspaceAgentPolicy;
use App\Notifications\Workspace\WorkspaceEventNotification;
use App\Services\Agents\Approvals\ChatApprovalLinks;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * An agent paused on actions that need someone's approval: sent to the
 * people who may decide them (`ActionApprovers::recipientsFor()`).
 *
 * The email carries a link per action to a one-page approve/reject form
 * (`AgentActionSignedDecisionController`), signed for this recipient and
 * valid until the action expires, so it can be answered from a phone
 * without opening the app. The link itself changes nothing (mail scanners
 * prefetch links) only submitting the form does.
 *
 * Slack channels get approve/reject buttons when the workspace allows
 * deciding from chat (`WorkspaceAgentPolicy::$allow_chat_approvals`).
 */
class AgentActionApprovalRequestedNotification extends WorkspaceEventNotification
{
    /** How many actions an email or chat message lists before summarising the rest. */
    public const int LISTED_ACTIONS = 5;

    /**
     * @param  Collection<int, AgentAction>  $actions
     */
    public function __construct(
        private readonly AgentSession $session,
        private readonly Collection $actions,
    ) {
        $agent = $session->agent;
        $count = $actions->count();

        parent::__construct(
            workspace: $session->workspace,
            event: NotificationEvent::AgentActionApprovalRequested,
            title: $count === 1
                ? "{$agent->name} wants to {$this->describe($actions->first())}"
                : "{$agent->name} is waiting for approval on {$count} actions",
            body: $actions->take(self::LISTED_ACTIONS)->map(fn (AgentAction $action): string => '• '.$this->describe($action))->implode("\n"),
            data: [
                'agent_id' => $agent->id,
                'agent_session_id' => $session->id,
                'action_ids' => $actions->modelKeys(),
            ],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->line("{$this->session->agent->name} paused and is waiting for a decision before it continues.");

        foreach ($this->actions->take(self::LISTED_ACTIONS) as $action) {
            $mail->line('**'.$this->describe($action).'**');

            if (filled($action->reason['detail'] ?? null)) {
                $mail->line('Why it asked: '.$action->reason['detail']);
            }

            $mail->action('Review and decide', URL::temporarySignedRoute(
                'agent-actions.signed-decision.show',
                $action->expires_at ?? now()->addDay(),
                ['action' => $action->id, 'user' => $notifiable->getKey()],
            ));
        }

        if ($this->actions->count() > self::LISTED_ACTIONS) {
            $mail->line('And '.($this->actions->count() - self::LISTED_ACTIONS).' more in the app.');
        }

        return $mail->line('If nobody decides in time, the action is cancelled and the agent is told.');
    }

    /**
     * @return array{workspace_id: string, channel_ids: array<int, int>, message: string, slack_blocks?: array<int, mixed>}
     */
    public function toWorkspaceChannel(object $notifiable): array
    {
        $payload = parent::toWorkspaceChannel($notifiable);

        if (! WorkspaceAgentPolicy::forWorkspace($this->workspace->id)->allow_chat_approvals) {
            return $payload;
        }

        return [...$payload, 'slack_blocks' => app(ChatApprovalLinks::class)->slackBlocks($this->title, $this->actions->take(self::LISTED_ACTIONS))];
    }

    private function describe(AgentAction $action): string
    {
        $arguments = collect($action->effectiveArguments())
            ->map(fn (mixed $value, string $key): string => "{$key}: ".Str::limit(is_scalar($value) ? (string) $value : (string) json_encode($value), 60))
            ->take(3)
            ->implode(', ');

        $tool = str_replace('_', ' ', $action->tool_name);

        return $arguments === '' ? "run {$tool}" : "run {$tool} ({$arguments})";
    }
}
