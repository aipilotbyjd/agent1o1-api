<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Referrals\ReferralProgramRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Referrals\ReferralProgramResource;
use App\Http\Responses\ApiResponse;
use App\Models\Referrals\ReferralProgram;
use App\Services\Referrals\ReferralAdmin;
use Illuminate\Http\Request;

/**
 * Create, tune, schedule and retire referral programs. Deleting is a soft
 * delete: referrals made under a program keep their terms and history.
 */
class ReferralProgramController extends Controller
{
    public function __construct(private readonly ReferralAdmin $admin) {}

    public function index(Request $request)
    {
        $programs = ReferralProgram::query()
            ->when($request->boolean('with_trashed'), fn ($query) => $query->withTrashed())
            ->withCount(['rules', 'referrals'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(['programs' => ReferralProgramResource::collection($programs)]);
    }

    public function store(ReferralProgramRequest $request)
    {
        $program = $this->admin->createProgram($request->validated(), $request->user('super-admin'));

        return ApiResponse::created(['program' => ReferralProgramResource::make($program->load('rules'))], 'Referral program created.');
    }

    public function show(ReferralProgram $program)
    {
        $program->load(['rules.plan'])->loadCount(['rules', 'referrals']);

        return ApiResponse::success(['program' => ReferralProgramResource::make($program)]);
    }

    public function update(ReferralProgramRequest $request, ReferralProgram $program)
    {
        $this->admin->updateProgram($program, $request->validated(), $request->user('super-admin'));

        return ApiResponse::success(['program' => ReferralProgramResource::make($program->refresh()->load('rules'))], 'Referral program updated.');
    }

    public function destroy(Request $request, ReferralProgram $program)
    {
        $this->admin->deleteProgram($program, $request->user('super-admin'));

        return ApiResponse::success(null, 'Referral program deleted.');
    }

    public function makeDefault(Request $request, ReferralProgram $program)
    {
        $this->admin->makeDefault($program, $request->user('super-admin'));

        return ApiResponse::success(['program' => ReferralProgramResource::make($program->refresh())], 'Default referral program changed.');
    }

    public function duplicate(Request $request, ReferralProgram $program)
    {
        $copy = $this->admin->duplicateProgram($program, $request->user('super-admin'));

        return ApiResponse::created(['program' => ReferralProgramResource::make($copy->load('rules'))], 'Referral program duplicated.');
    }
}
