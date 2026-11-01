<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Agents\ResolveAgentActionsAction;
use App\Http\Controllers\Controller;
use App\Models\Agents\AgentAction;
use App\Models\Agents\WorkspaceAgentPolicy;
use App\Models\Notifications\NotificationChannel;
use App\Services\Agents\Approvals\ChatApprovalLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Slack's interactivity endpoint for approve/reject buttons on approval
 * messages (`ChatApprovalLinks`). Point the Slack app's Interactivity
 * Request URL here and store its signing secret on the workspace's Slack
 * notification channel.
 *
 * Two checks before anything is decided: Slack's request signature, against
 * a signing secret stored on one of the action's workspace's Slack
 * channels, proves the click came from that Slack app; the button's
 * encrypted value proves which action and decision it was. The workspace
 * must also allow deciding from chat at all. Whoever clicked is recorded by
 * their Slack name: they may have no account here.
 */
class SlackAgentActionController extends Controller
{
    /**
     * Slack's own replay window.
     */
    private const int MAX_AGE_SECONDS = 300;

    public function __invoke(Request $request, ChatApprovalLinks $links, ResolveAgentActionsAction $resolve): JsonResponse
    {
        $payload = json_decode((string) $request->input('payload'), true);
        $button = is_array($payload) ? ($payload['actions'][0]['value'] ?? null) : null;
        $decoded = is_string($button) ? $links->decode($button) : null;

        abort_if($decoded === null, 400, 'Not an approval button.');

        $action = AgentAction::query()->find($decoded['action_id']);

        if ($action === null) {
            return $this->reply('This action no longer exists.');
        }

        abort_unless($this->signedBySlack($request, $action->workspace_id), 401, 'Invalid Slack signature.');
        abort_unless(WorkspaceAgentPolicy::forWorkspace($action->workspace_id)->allow_chat_approvals, 403, 'This workspace does not allow deciding agent actions from chat.');

        if (! $action->status->isAwaitingDecision()) {
            return $this->reply("Already {$action->status->value}.");
        }

        if ($action->hasNamedApprovers()) {
            return $this->reply('Only the approvers named for this tool can decide it, in the app.');
        }

        $slackUser = $payload['user']['username'] ?? $payload['user']['name'] ?? $payload['user']['id'] ?? 'unknown';

        $resolve->execute(null, [[
            'action_id' => $action->id,
            'decision' => $decoded['decision'] === ChatApprovalLinks::APPROVE ? 'approve' : 'reject',
        ]], 'slack', actorLabel: "via Slack by {$slackUser}");

        return $this->reply(($decoded['decision'] === ChatApprovalLinks::APPROVE ? 'Approved' : 'Rejected')." {$action->tool_name} (by {$slackUser}).");
    }

    private function signedBySlack(Request $request, string $workspaceId): bool
    {
        $timestamp = (string) $request->header('X-Slack-Request-Timestamp');
        $signature = (string) $request->header('X-Slack-Signature');

        if ($timestamp === '' || $signature === '' || abs(time() - (int) $timestamp) > self::MAX_AGE_SECONDS) {
            return false;
        }

        $base = "v0:{$timestamp}:{$request->getContent()}";

        return NotificationChannel::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', 'slack')
            ->where('is_active', true)
            ->get()
            ->contains(function (NotificationChannel $channel) use ($base, $signature): bool {
                $secret = $channel->config['signing_secret'] ?? null;

                return is_string($secret) && $secret !== '' && hash_equals('v0='.hash_hmac('sha256', $base, $secret), $signature);
            });
    }

    private function reply(string $text): JsonResponse
    {
        return response()->json(['response_type' => 'ephemeral', 'replace_original' => false, 'text' => $text]);
    }
}
