<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DiscoverySource;
use App\Enums\JobRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Onboarding\InviteTeamRequest;
use App\Http\Requests\Api\V1\Onboarding\SaveDiscoveryRequest;
use App\Http\Requests\Api\V1\Onboarding\SaveRoleRequest;
use App\Http\Requests\Api\V1\Onboarding\SelectPlanRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\Plan;
use App\Services\InvitationService;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboarding,
        private readonly InvitationService $invitations,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->successResponse(
            'Onboarding state retrieved.',
            $this->onboarding->state($request->user()),
        );
    }

    public function saveRole(SaveRoleRequest $request): JsonResponse
    {
        $role = JobRole::from($request->validated('job_role'));
        $this->onboarding->saveRole($request->user(), $role);

        return $this->successResponse(
            'Role saved.',
            $this->onboarding->state($request->user()),
        );
    }

    public function saveDiscovery(SaveDiscoveryRequest $request): JsonResponse
    {
        $source = DiscoverySource::from($request->validated('discovery_source'));
        $this->onboarding->saveDiscovery($request->user(), $source);

        return $this->successResponse(
            'Discovery source saved.',
            $this->onboarding->state($request->user()),
        );
    }

    public function inviteTeam(InviteTeamRequest $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        if (! $workspace) {
            return $this->errorResponse('No active workspace. Complete step 2 first.', 422);
        }

        $this->invitations->sendBulk($workspace, $user, $request->validated());

        return $this->successResponse(
            'Invitations sent.',
            $this->onboarding->state($user),
        );
    }

    public function selectPlan(SelectPlanRequest $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        if (! $workspace) {
            return $this->errorResponse('No active workspace. Complete step 2 first.', 422);
        }

        $plan = Plan::where('slug', $request->validated('plan_slug'))->firstOrFail();

        if ($plan->price_monthly === 0) {
            // Free plan — mark as chosen without going through Stripe.
            $workspace->subscription()->updateOrCreate(
                ['workspace_id' => $workspace->id],
                [
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'current_period_start' => now(),
                    'current_period_end' => now()->addYear(),
                ],
            );

            return $this->successResponse(
                'Free plan activated.',
                $this->onboarding->state($user),
            );
        }

        // Paid plan — return a Stripe checkout URL via the existing billing flow.
        return $this->errorResponse(
            'Paid plans require Stripe checkout. Use POST /api/v1/workspaces/{workspace}/subscriptions/checkout.',
            422,
        );
    }

    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->onboarding_dismissed_at === null) {
            $user->update(['onboarding_dismissed_at' => now()]);
        }

        return $this->successResponse(
            'Onboarding complete.',
            new UserResource($user->load('currentWorkspace')),
        );
    }
}
