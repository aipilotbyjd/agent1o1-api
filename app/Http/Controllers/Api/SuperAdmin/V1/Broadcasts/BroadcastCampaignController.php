<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Broadcasts;

use App\Enums\Broadcasts\BroadcastStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Broadcasts\BroadcastCampaignRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Broadcasts\BroadcastCampaignResource;
use App\Http\Responses\ApiResponse;
use App\Models\Broadcasts\BroadcastCampaign;
use App\Services\Broadcasts\BroadcastCampaignService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Email campaigns: drafting, and reading how a send is going. Sending itself
 * is `BroadcastCampaignActionController`.
 */
class BroadcastCampaignController extends Controller
{
    public function __construct(private readonly BroadcastCampaignService $campaigns) {}

    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', Rule::enum(BroadcastStatus::class)]]);

        $campaigns = BroadcastCampaign::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($inner) => $inner->whereLike('name', '%'.$request->string('q').'%')->orWhereLike('subject', '%'.$request->string('q').'%')))
            ->latest()
            ->orderBy('id')
            ->paginate(min($request->integer('per_page', 25), 100));

        return ApiResponse::paginated(BroadcastCampaignResource::collection($campaigns));
    }

    public function store(BroadcastCampaignRequest $request)
    {
        $campaign = $this->campaigns->create($request->user('super-admin'), $request->validated());

        return ApiResponse::created((new BroadcastCampaignResource($campaign))->resolve($request), 'Campaign saved as a draft.');
    }

    public function show(Request $request, BroadcastCampaign $campaign)
    {
        return ApiResponse::success((new BroadcastCampaignResource($campaign))->resolve($request));
    }

    public function update(BroadcastCampaignRequest $request, BroadcastCampaign $campaign)
    {
        $campaign = $this->campaigns->update($request->user('super-admin'), $campaign, $request->validated());

        return ApiResponse::success((new BroadcastCampaignResource($campaign))->resolve($request), 'Campaign updated.');
    }

    public function destroy(Request $request, BroadcastCampaign $campaign)
    {
        $this->campaigns->delete($request->user('super-admin'), $campaign);

        return ApiResponse::success(null, 'Campaign deleted.');
    }
}
