<?php

namespace App\Http\Controllers\Api\Internal\V1\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Referrals\UpdateReferralCodeRequest;
use App\Http\Resources\Api\Internal\V1\Referrals\ReferralRewardResource;
use App\Http\Resources\Api\Internal\V1\Referrals\ReferredUserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Referrals\ReferralCode;
use App\Services\Referrals\ReferralCodes;
use App\Services\Referrals\ReferralRecipientWorkspace;
use App\Services\Referrals\ReferralSettings;
use App\Services\Referrals\ReferralStats;
use Illuminate\Http\Request;

/**
 * The signed-in user's side of the referral program: their code and share
 * link, the program's current terms, their numbers, and who they referred.
 */
class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralCodes $codes,
        private readonly ReferralStats $stats,
        private readonly ReferralSettings $settings,
        private readonly ReferralRecipientWorkspace $workspaces,
    ) {}

    public function me(Request $request)
    {
        $user = $request->user();
        $code = $this->codes->forUser($user);

        return ApiResponse::success($this->profile($request, $code));
    }

    public function update(UpdateReferralCodeRequest $request)
    {
        $user = $request->user();
        $code = $this->codes->forUser($user);

        if ($request->has('code') && $this->codes->normalize($request->validated('code')) !== $code->code) {
            $code->fill([
                'code' => $this->codes->normalize($request->validated('code')),
                'custom_code_set_at' => now(),
            ]);
        }

        if ($request->has('reward_workspace_id')) {
            $code->reward_workspace_id = $request->validated('reward_workspace_id');
        }

        $code->save();

        return ApiResponse::success($this->profile($request, $code->refresh()), 'Referral settings updated.');
    }

    public function program(Request $request)
    {
        $program = $this->stats->programFor($this->codes->forUser($request->user()));

        if ($program === null || ! $this->settings->enabled()) {
            return ApiResponse::success(['enabled' => false, 'program' => null, 'terms' => []]);
        }

        return ApiResponse::success([
            'enabled' => true,
            'program' => [
                'name' => $program->name,
                'description' => $program->description,
                'ends_at' => $program->ends_at,
            ],
            'terms' => $this->stats->terms($program),
        ]);
    }

    public function stats(Request $request)
    {
        $user = $request->user();

        return ApiResponse::success($this->stats->forReferrer($user, $this->codes->forUser($user)));
    }

    public function index(Request $request)
    {
        $referrals = $request->user()->referralsMade()
            ->with('referredUser:id,email')
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated(ReferredUserResource::collection($referrals));
    }

    public function rewards(Request $request)
    {
        $rewards = $request->user()->referralRewards()
            ->with('plan')
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated(ReferralRewardResource::collection($rewards));
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(Request $request, ReferralCode $code): array
    {
        $user = $request->user();
        $workspace = $this->workspaces->forReferrer($user, $code);
        $program = $this->stats->programFor($code);

        return [
            'enabled' => $this->settings->enabled() && $program !== null,
            'code' => $code->code,
            'is_active' => $code->is_active,
            'share_url' => rtrim((string) config('app.frontend_url'), '/').'/?ref='.urlencode($code->code),
            'can_customize_code' => $code->custom_code_set_at === null,
            'reward_workspace' => $workspace === null ? null : ['id' => $workspace->id, 'name' => $workspace->name],
            'program' => $program === null ? null : ['id' => $program->id, 'name' => $program->name],
            'next_milestone' => $this->stats->nextMilestone($user, $program),
        ];
    }
}
