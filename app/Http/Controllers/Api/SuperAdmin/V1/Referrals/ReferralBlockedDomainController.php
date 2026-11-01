<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Referrals\StoreReferralBlockedDomainRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Referrals\ReferralBlockedDomainResource;
use App\Http\Responses\ApiResponse;
use App\Models\Referrals\ReferralBlockedDomain;
use App\Services\Referrals\ReferralAdmin;
use Illuminate\Http\Request;

class ReferralBlockedDomainController extends Controller
{
    public function __construct(private readonly ReferralAdmin $admin) {}

    public function index()
    {
        return ApiResponse::success(['domains' => ReferralBlockedDomainResource::collection(ReferralBlockedDomain::query()->orderBy('domain')->get())]);
    }

    public function store(StoreReferralBlockedDomainRequest $request)
    {
        $domain = $this->admin->blockDomain($request->validated('domain'), $request->validated('reason'), $request->user('super-admin'));

        return ApiResponse::created(['domain' => ReferralBlockedDomainResource::make($domain)], 'Domain blocked.');
    }

    public function destroy(Request $request, ReferralBlockedDomain $domain)
    {
        $this->admin->unblockDomain($domain, $request->user('super-admin'));

        return ApiResponse::success(null, 'Domain unblocked.');
    }
}
