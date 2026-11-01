<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Enums\Billing\Feature;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Assistant\AssistantPhoneNumber;
use App\Models\Assistant\AssistantSlackInstall;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\Branding\BrandRepository;
use App\Services\Assistant\Channels\PhoneVerification;
use App\Services\Assistant\Channels\TwilioSms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Where else the owner can reach the assistant: its email address and the
 * Slack app (installed per Slack workspace by an admin).
 */
class AssistantChannelController extends Controller
{
    private const int INSTALL_LINK_MINUTES = 15;

    public function index(Request $request, Workspace $workspace, BrandRepository $brands, TwilioSms $twilio): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $install = AssistantSlackInstall::query()->where('workspace_id', $workspace->id)->first();

        return ApiResponse::success([
            'email' => [
                'address' => $brands->current($workspace)->email(),
                'enabled' => filled(config('assistant.channels.email.inbound_token')),
            ],
            'slack' => [
                'available' => $this->slackConfigured(),
                'installed' => $install !== null,
                'team_name' => $install?->team_name,
                'team_id' => $install?->slack_team_id,
            ],
            'sms' => [
                'available' => $twilio->configured(),
                'plan' => (bool) $workspace->currentPlan()?->hasFeature(Feature::AssistantSms),
                'number' => config('assistant.channels.sms.from'),
                'phone' => AssistantPhoneNumber::query()->where('user_id', $request->user()->id)->whereNotNull('verified_at')->value('phone'),
            ],
        ]);
    }

    public function smsStart(Request $request, Workspace $workspace, PhoneVerification $verification, TwilioSms $twilio): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);
        abort_unless($twilio->configured(), 404, 'Texting is not set up on this server.');
        abort_unless((bool) $workspace->currentPlan()?->hasFeature(Feature::AssistantSms), 402, 'Texting needs a plan that includes it.');

        $verification->start($request->user(), (string) $request->validate(['phone' => ['required', 'string', 'max:20']])['phone']);

        return ApiResponse::success([], 'Code sent.');
    }

    public function smsConfirm(Request $request, Workspace $workspace, PhoneVerification $verification): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $number = $verification->confirm($request->user(), (string) $request->validate(['code' => ['required', 'string', 'max:10']])['code']);

        return ApiResponse::success(['phone' => $number->phone], 'Number verified.');
    }

    public function smsRemove(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        AssistantPhoneNumber::query()->where('user_id', $request->user()->id)->delete();

        return ApiResponse::success([], 'Number removed.');
    }

    /**
     * Installing the app connects a whole Slack workspace, so it needs an
     * admin of this one.
     */
    public function slackInstallUrl(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::ConnectorManage);
        abort_unless($this->slackConfigured(), 404, 'Slack is not set up on this server.');

        $state = Crypt::encrypt([
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->id,
            'expires' => now()->addMinutes(self::INSTALL_LINK_MINUTES)->getTimestamp(),
        ]);

        return ApiResponse::success(['url' => 'https://slack.com/oauth/v2/authorize?'.http_build_query([
            'client_id' => config('assistant.channels.slack.client_id'),
            'scope' => config('assistant.channels.slack.scopes'),
            'redirect_uri' => route('hooks.assistant.slack.oauth'),
            'state' => $state,
        ])]);
    }

    private function slackConfigured(): bool
    {
        return filled(config('assistant.channels.slack.client_id'))
            && filled(config('assistant.channels.slack.client_secret'))
            && filled(config('assistant.channels.slack.signing_secret'));
    }
}
