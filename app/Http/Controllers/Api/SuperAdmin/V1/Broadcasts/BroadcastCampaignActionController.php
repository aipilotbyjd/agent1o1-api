<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Broadcasts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Broadcasts\ScheduleBroadcastRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Broadcasts\StartBroadcastRequest;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Broadcasts\BroadcastCampaignResource;
use App\Http\Responses\ApiResponse;
use App\Models\Broadcasts\BroadcastCampaign;
use App\Services\Broadcasts\BroadcastCampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * What changes a campaign's course: test it on yourself, start it, schedule
 * it, stop it, retry what failed. Starting and scheduling need a fresh
 * authenticator code and the previewed recipient count.
 */
class BroadcastCampaignActionController extends Controller
{
    public function __construct(private readonly BroadcastCampaignService $campaigns) {}

    public function testSend(Request $request, BroadcastCampaign $campaign)
    {
        $admin = $request->user('super-admin');

        $this->campaigns->testSend($admin, $campaign);

        return ApiResponse::success(null, "A test copy was sent to {$admin->email}.");
    }

    public function start(StartBroadcastRequest $request, BroadcastCampaign $campaign)
    {
        $campaign = $this->campaigns->start(
            $request->user('super-admin'),
            $campaign,
            $request->integer('expected_recipient_count'),
            $request->validated('reason'),
        );

        return ApiResponse::success((new BroadcastCampaignResource($campaign))->resolve($request), 'Campaign is sending.');
    }

    public function schedule(ScheduleBroadcastRequest $request, BroadcastCampaign $campaign)
    {
        $campaign = $this->campaigns->schedule(
            $request->user('super-admin'),
            $campaign,
            Carbon::parse($request->validated('scheduled_at')),
            $request->integer('expected_recipient_count'),
            $request->validated('reason'),
        );

        return ApiResponse::success((new BroadcastCampaignResource($campaign))->resolve($request), 'Campaign scheduled.');
    }

    public function cancel(ReasonRequest $request, BroadcastCampaign $campaign)
    {
        $campaign = $this->campaigns->cancel($request->user('super-admin'), $campaign, $request->validated('reason'));

        return ApiResponse::success((new BroadcastCampaignResource($campaign))->resolve($request), 'Campaign canceled. Messages already sent cannot be recalled.');
    }

    public function retryFailed(ReasonRequest $request, BroadcastCampaign $campaign)
    {
        $campaign = $this->campaigns->retryFailed($request->user('super-admin'), $campaign, $request->validated('reason'));

        return ApiResponse::success((new BroadcastCampaignResource($campaign))->resolve($request), 'Retrying the failed messages.');
    }
}
