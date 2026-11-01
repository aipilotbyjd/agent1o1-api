<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Referrals\UpdateAdminReferralCodeRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Referrals\AdminReferralCodeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Referrals\ReferralCode;
use App\Services\Referrals\ReferralAdmin;
use Illuminate\Http\Request;

/**
 * Per-referrer overrides: a custom code, a pinned program, a reward
 * multiplier, a use limit or expiry, or switching a code off for abuse.
 */
class AdminReferralCodeController extends Controller
{
    public function __construct(private readonly ReferralAdmin $admin) {}

    public function index(Request $request)
    {
        $search = $request->string('search')->trim()->toString();

        $codes = ReferralCode::query()
            ->with('user:id,name,email')
            ->withCount(['referrals', 'visits'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($query) => $query->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            }))
            ->when($request->filled('program_id'), fn ($query) => $query->where('program_id', $request->string('program_id')))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated(AdminReferralCodeResource::collection($codes));
    }

    public function update(UpdateAdminReferralCodeRequest $request, ReferralCode $code)
    {
        $this->admin->updateCode($code, $request->validated(), $request->user('super-admin'));

        return ApiResponse::success(['code' => AdminReferralCodeResource::make($code->refresh()->load('user'))], 'Referral code updated.');
    }
}
