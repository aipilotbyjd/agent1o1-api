<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Referrals\ReferralRewardRuleRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Referrals\ReorderReferralRewardRulesRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Referrals\ReferralRewardRuleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralRewardRule;
use App\Services\Referrals\ReferralAdmin;
use Illuminate\Http\Request;

/**
 * Reward rules: "when X happens, give Y to Z". Edits never change rewards
 * already earned: each reward keeps a snapshot of its rule.
 */
class ReferralRewardRuleController extends Controller
{
    public function __construct(private readonly ReferralAdmin $admin) {}

    public function index(ReferralProgram $program)
    {
        return ApiResponse::success(['rules' => ReferralRewardRuleResource::collection($program->rules()->with('plan')->get())]);
    }

    public function store(ReferralRewardRuleRequest $request, ReferralProgram $program)
    {
        $rule = $this->admin->createRule($program, $request->validated(), $request->user('super-admin'));

        return ApiResponse::created(['rule' => ReferralRewardRuleResource::make($rule->load('plan'))], 'Reward rule created.');
    }

    public function update(ReferralRewardRuleRequest $request, ReferralRewardRule $rule)
    {
        $this->admin->updateRule($rule, $request->validated(), $request->user('super-admin'));

        return ApiResponse::success(['rule' => ReferralRewardRuleResource::make($rule->refresh()->load('plan'))], 'Reward rule updated.');
    }

    public function destroy(Request $request, ReferralRewardRule $rule)
    {
        $this->admin->deleteRule($rule, $request->user('super-admin'));

        return ApiResponse::success(null, 'Reward rule deleted.');
    }

    public function toggle(Request $request, ReferralRewardRule $rule)
    {
        $this->admin->toggleRule($rule, $request->user('super-admin'));

        return ApiResponse::success(['rule' => ReferralRewardRuleResource::make($rule)], $rule->is_active ? 'Reward rule switched on.' : 'Reward rule switched off.');
    }

    public function reorder(ReorderReferralRewardRulesRequest $request, ReferralProgram $program)
    {
        $this->admin->reorderRules($program, $request->validated('rule_ids'), $request->user('super-admin'));

        return ApiResponse::success(['rules' => ReferralRewardRuleResource::collection($program->rules()->with('plan')->get())], 'Reward rules reordered.');
    }
}
