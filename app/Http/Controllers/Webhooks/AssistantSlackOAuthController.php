<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Assistant\AssistantSlackInstall;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Where Slack sends an admin back after "Add to Slack": the code becomes
 * the app's bot token for that Slack workspace, linked to the agent1o1
 * workspace named in the signed `state`.
 */
class AssistantSlackOAuthController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        try {
            $state = Crypt::decrypt((string) $request->query('state'));
        } catch (DecryptException) {
            abort(400, 'Invalid install link.');
        }

        abort_if(! is_array($state) || ($state['expires'] ?? 0) < time(), 400, 'This install link has expired. Start again from the app.');

        $back = config('app.frontend_url')."/{$state['workspace_id']}/assistant";

        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->away("{$back}?slack=cancelled");
        }

        $response = Http::asForm()->post('https://slack.com/api/oauth.v2.access', [
            'client_id' => config('assistant.channels.slack.client_id'),
            'client_secret' => config('assistant.channels.slack.client_secret'),
            'code' => $request->query('code'),
            'redirect_uri' => route('hooks.assistant.slack.oauth'),
        ]);

        if ($response->json('ok') !== true) {
            report(new RuntimeException('Slack install failed: '.($response->json('error') ?? $response->body())));

            return redirect()->away("{$back}?slack=failed");
        }

        AssistantSlackInstall::query()->updateOrCreate(
            ['slack_team_id' => (string) $response->json('team.id')],
            [
                'team_name' => $response->json('team.name'),
                'bot_token' => (string) $response->json('access_token'),
                'bot_user_id' => $response->json('bot_user_id'),
                'workspace_id' => $state['workspace_id'],
                'installed_by' => $state['user_id'],
            ],
        );

        return redirect()->away("{$back}?slack=connected");
    }
}
