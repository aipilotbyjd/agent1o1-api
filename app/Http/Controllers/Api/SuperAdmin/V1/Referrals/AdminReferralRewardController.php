<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Referrals;

use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralRewardType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Referrals\ReasonRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Referrals\StoreManualReferralRewardRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Referrals\AdminReferralRewardResource;
use App\Http\Responses\ApiResponse;
use App\Models\Referrals\ReferralReward;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Referrals\ReferralAdmin;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The reward ledger: review what is awaiting approval or on hold, approve,
 * grant early, revoke, or hand out a goodwill reward.
 */
class AdminReferralRewardController extends Controller
{
    public function __construct(private readonly ReferralAdmin $admin) {}

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(ReferralRewardStatus::class)],
            'reward_type' => ['nullable', Rule::enum(ReferralRewardType::class)],
        ]);

        $rewards = ReferralReward::query()
            ->with(['recipient:id,name,email', 'plan'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('reward_type'), fn ($query) => $query->where('reward_type', $request->string('reward_type')))
            ->when($request->filled('recipient_user_id'), fn ($query) => $query->where('recipient_user_id', $request->string('recipient_user_id')))
            ->when($request->filled('referral_id'), fn ($query) => $query->where('referral_id', $request->string('referral_id')))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated(AdminReferralRewardResource::collection($rewards));
    }

    /**
     * Approves a reward held for manual review; see `ReferralAdmin::approveReward()`.
     */
    public function approve(Request $request, ReferralReward $reward)
    {
        $this->admin->approveReward($reward, $request->user('super-admin'));

        return ApiResponse::success(['reward' => AdminReferralRewardResource::make($reward->refresh()->load('recipient', 'plan'))], 'Reward approved.');
    }

    public function grantNow(Request $request, ReferralReward $reward)
    {
        $this->admin->grantRewardNow($reward, $request->user('super-admin'));

        return ApiResponse::success(['reward' => AdminReferralRewardResource::make($reward->refresh()->load('recipient', 'plan'))], 'Reward granted.');
    }

    public function revoke(ReasonRequest $request, ReferralReward $reward)
    {
        $this->admin->revokeReward($reward, $request->validated('reason'), $request->user('super-admin'));

        return ApiResponse::success(['reward' => AdminReferralRewardResource::make($reward->refresh()->load('recipient', 'plan'))], 'Reward revoked.');
    }

    public function store(StoreManualReferralRewardRequest $request)
    {
        $data = $request->validated();

        $reward = $this->admin->grantManualReward(
            User::query()->findOrFail($data['user_id']),
            isset($data['workspace_id']) ? Workspace::query()->findOrFail($data['workspace_id']) : null,
            [...$data, 'reward_type' => ReferralRewardType::from($data['reward_type'])],
            $data['notes'] ?? null,
            $request->user('super-admin'),
        );

        return ApiResponse::created(['reward' => AdminReferralRewardResource::make($reward->load('recipient', 'plan'))], 'Reward granted.');
    }
}
