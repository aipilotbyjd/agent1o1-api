<?php

namespace App\Services\Agents\Approvals;

use App\Models\Agents\AgentAction;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Approve/reject buttons for a chat message about waiting actions. Each
 * button's value is encrypted, so the interaction endpoint
 * (`SlackAgentActionController`) can trust which action and decision it
 * names: Slack only signs the request, not what the buttons say.
 */
class ChatApprovalLinks
{
    public const string APPROVE = 'approve';

    public const string REJECT = 'reject';

    /**
     * Slack Block Kit for an approval request.
     *
     * @param  Collection<int, AgentAction>  $actions
     * @return list<array<string, mixed>>
     */
    public function slackBlocks(string $title, Collection $actions): array
    {
        $blocks = [['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => Str::limit($title, 150)]]];

        foreach ($actions as $action) {
            $arguments = Str::limit((string) json_encode($action->effectiveArguments(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 500);

            $blocks[] = [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => "*{$action->tool_name}*\n```{$arguments}```"],
            ];

            // Whoever clicks is only known by their Slack name, which can't
            // satisfy a rule naming who may approve.
            if ($action->hasNamedApprovers()) {
                $blocks[] = [
                    'type' => 'context',
                    'elements' => [['type' => 'mrkdwn', 'text' => 'Only its named approvers can decide this one, in the app.']],
                ];

                continue;
            }

            $blocks[] = [
                'type' => 'actions',
                'block_id' => "agent_action_{$action->id}",
                'elements' => [
                    $this->button('Approve', 'primary', $action, self::APPROVE),
                    $this->button('Reject', 'danger', $action, self::REJECT),
                ],
            ];
        }

        return $blocks;
    }

    /**
     * @return array{action_id: string, decision: string}|null
     */
    public function decode(string $value): ?array
    {
        try {
            $payload = json_decode(Crypt::decryptString($value), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($payload) || ! isset($payload['action_id'], $payload['decision'])) {
            return null;
        }

        return ['action_id' => (string) $payload['action_id'], 'decision' => (string) $payload['decision']];
    }

    /**
     * @return array<string, mixed>
     */
    private function button(string $label, string $style, AgentAction $action, string $decision): array
    {
        return [
            'type' => 'button',
            'text' => ['type' => 'plain_text', 'text' => $label],
            'style' => $style,
            'action_id' => "agent_action_{$decision}",
            'value' => Crypt::encryptString((string) json_encode(['action_id' => $action->id, 'decision' => $decision])),
        ];
    }
}
