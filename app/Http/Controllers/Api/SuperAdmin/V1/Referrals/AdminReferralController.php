<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Referrals;

use App\Enums\Referrals\ReferralStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Referrals\ReasonRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Referrals\AdminReferralResource;
use App\Http\Responses\ApiResponse;
use App\Models\Referrals\Referral;
use App\Services\Referrals\ReferralAdmin;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminReferralController extends Controller
{
    public function __construct(private readonly ReferralAdmin $admin) {}

    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', Rule::enum(ReferralStatus::class)]]);

        $referrals = Referral::query()
            ->with(['code:id,code', 'referrer:id,name,email', 'referredUser:id,name,email'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('program_id'), fn ($query) => $query->where('program_id', $request->string('program_id')))
            ->when($request->filled('referrer_user_id'), fn ($query) => $query->where('referrer_user_id', $request->string('referrer_user_id')))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated(AdminReferralResource::collection($referrals));
    }

    public function show(Referral $referral)
    {
        $referral->load(['code:id,code', 'referrer:id,name,email', 'referredUser:id,name,email', 'rewards.plan'])->loadCount('payments');

        return ApiResponse::success(['referral' => AdminReferralResource::make($referral)]);
    }

    /**
     * Marks a referral fraudulent (or otherwise ineligible) and withdraws
     * every reward it produced.
     */
    public function reject(ReasonRequest $request, Referral $referral)
    {
        $this->admin->rejectReferral($referral, $request->validated('reason'), $request->user('super-admin'));

        return ApiResponse::success(['referral' => AdminReferralResource::make($referral->refresh()->load('rewards'))], 'Referral rejected.');
    }

    public function restore(Request $request, Referral $referral)
    {
        $this->admin->restoreReferral($referral, $request->user('super-admin'));

        return ApiResponse::success(['referral' => AdminReferralResource::make($referral->refresh())], 'Referral restored.');
    }
}
