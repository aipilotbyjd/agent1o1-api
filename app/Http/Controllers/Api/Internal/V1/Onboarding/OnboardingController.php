<?php

namespace App\Http\Controllers\Api\Internal\V1\Onboarding;

use App\Enums\Onboarding\DiscoverySource;
use App\Enums\Onboarding\JobRole;
use App\Enums\Onboarding\OnboardingStep;
use App\Enums\Workspaces\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Onboarding\ContinueStepRequest;
use App\Http\Requests\Api\Internal\V1\Onboarding\InviteTeamRequest;
use App\Http\Requests\Api\Internal\V1\Onboarding\SelectPlanRequest;
use App\Http\Requests\Api\Internal\V1\Onboarding\SelectRoleRequest;
use App\Http\Requests\Api\Internal\V1\Onboarding\SkipStepRequest;
use App\Http\Requests\Api\Internal\V1\Onboarding\SubmitDiscoveryRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboarding,
    ) {}

    public function state(Request $request)
    {
        return ApiResponse::success($this->onboarding->state($request->user()));
    }

    public function inviteTeam(InviteTeamRequest $request)
    {
        $state = $this->onboarding->inviteTeam(
            $request->user(),
            $request->validated('emails'),
            Role::from($request->validated('role')),
            $request->validated('personal_note'),
        );

        return ApiResponse::success($state, 'Invites sent successfully.');
    }

    public function selectRole(SelectRoleRequest $request)
    {
        $state = $this->onboarding->selectRole($request->user(), JobRole::from($request->validated('job_role')));

        return ApiResponse::success($state, 'Role saved successfully.');
    }

    public function selectPlan(SelectPlanRequest $request)
    {
        $state = $this->onboarding->selectPlan($request->user(), $request->validated('plan_slug'));

        return ApiResponse::success($state, 'Plan selected successfully.');
    }

    public function submitDiscovery(SubmitDiscoveryRequest $request)
    {
        $state = $this->onboarding->submitDiscovery($request->user(), DiscoverySource::from($request->validated('discovery_source')));

        return ApiResponse::success($state, 'Thanks for letting us know.');
    }

    public function continue(ContinueStepRequest $request)
    {
        $state = $this->onboarding->continue($request->user(), OnboardingStep::from($request->validated('step')));

        return ApiResponse::success($state, 'Progress saved.');
    }

    public function skip(SkipStepRequest $request)
    {
        $state = $this->onboarding->skip($request->user(), OnboardingStep::from($request->validated('step')));

        return ApiResponse::success($state, 'Step skipped.');
    }

    public function complete(Request $request)
    {
        $state = $this->onboarding->complete($request->user());

        return ApiResponse::success($state, 'Onboarding completed.');
    }

    public function dismiss(Request $request)
    {
        $this->onboarding->dismiss($request->user());

        return ApiResponse::success(message: 'Onboarding dismissed.');
    }
}
