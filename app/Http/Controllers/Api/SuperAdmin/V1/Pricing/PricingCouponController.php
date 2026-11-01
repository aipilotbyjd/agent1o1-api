<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Pricing\StoreCouponRequest;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Pricing\PricingCouponResource;
use App\Http\Responses\ApiResponse;
use App\Models\Billing\PricingCoupon;
use App\Services\SuperAdmin\Pricing\SuperAdminCouponService;
use Illuminate\Http\Request;

class PricingCouponController extends Controller
{
    public function __construct(private readonly SuperAdminCouponService $coupons) {}

    public function index(Request $request)
    {
        $coupons = PricingCoupon::query()
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(min($request->integer('per_page', 50), 100));

        return ApiResponse::cursorPaginated(PricingCouponResource::collection($coupons));
    }

    public function show(Request $request, PricingCoupon $coupon)
    {
        return ApiResponse::success((new PricingCouponResource($this->coupons->refreshRedemptions($coupon)))->resolve($request));
    }

    public function store(StoreCouponRequest $request)
    {
        $coupon = $this->coupons->create(
            $request->user('super-admin'),
            $request->safe()->except(['reason', 'totp_code']),
            $request->validated('reason'),
        );

        return ApiResponse::created((new PricingCouponResource($coupon))->resolve($request), 'Coupon created.');
    }

    public function activate(StepUpRequest $request, PricingCoupon $coupon)
    {
        $coupon = $this->coupons->setActive($request->user('super-admin'), $coupon, true, $request->validated('reason'));

        return ApiResponse::success((new PricingCouponResource($coupon))->resolve($request), 'Coupon activated.');
    }

    public function deactivate(StepUpRequest $request, PricingCoupon $coupon)
    {
        $coupon = $this->coupons->setActive($request->user('super-admin'), $coupon, false, $request->validated('reason'));

        return ApiResponse::success((new PricingCouponResource($coupon))->resolve($request), 'Coupon deactivated.');
    }
}
