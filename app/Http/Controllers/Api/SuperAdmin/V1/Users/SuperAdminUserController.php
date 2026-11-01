<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Users\SuperAdminUserResource;
use App\Http\Responses\ApiResponse;
use App\Models\SuperAdmin\SuperAdminAccountTag;
use App\Models\User;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use App\Services\SuperAdmin\Customers\CustomerAnnotations;
use App\Services\SuperAdmin\Customers\CustomerSubjects;
use App\Services\SuperAdmin\Users\SuperAdminUserService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuperAdminUserController extends Controller
{
    public function __construct(
        private readonly SuperAdminUserService $users,
        private readonly SuperAdminAuditLogger $audit,
        private readonly CustomerAnnotations $annotations,
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['active', 'suspended', 'unverified'])],
            'sort' => ['nullable', Rule::in(['created_at', '-created_at', 'name', '-name', 'email', '-email'])],
            'tag' => ['nullable', 'string', 'max:24'],
        ]);

        $sort = (string) $request->string('sort', '-created_at');

        $users = User::query()
            ->withCount('workspaces')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';

                $query->where(fn ($inner) => $inner->whereLike('email', $term)->orWhereLike('name', $term));
            })
            ->when($request->filled('tag'), fn ($query) => $query->whereIn('id', SuperAdminAccountTag::query()->where('subject_type', CustomerSubjects::USER)->where('tag', mb_strtolower($request->string('tag')->toString()))->select('subject_id')))
            ->when($request->string('status')->toString() === 'suspended', fn ($query) => $query->whereNotNull('suspended_at'))
            ->when($request->string('status')->toString() === 'active', fn ($query) => $query->whereNull('suspended_at'))
            ->when($request->string('status')->toString() === 'unverified', fn ($query) => $query->whereNull('email_verified_at'))
            ->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate(min($request->integer('per_page', 25), 100));

        $this->annotations->attach(CustomerSubjects::USER, $users->getCollection());

        return ApiResponse::paginated(SuperAdminUserResource::collection($users));
    }

    public function show(Request $request, User $user)
    {
        $user->loadCount('workspaces')->load('workspaces:id,name,slug,suspended_at');
        $this->annotations->attach(CustomerSubjects::USER, [$user]);

        $this->audit->record($request->user('super-admin'), 'users.viewed', $user);

        return ApiResponse::success((new SuperAdminUserResource($user))->resolve($request));
    }

    public function suspend(ReasonRequest $request, User $user)
    {
        $user = $this->users->suspend($request->user('super-admin'), $user, $request->validated('reason'));

        return ApiResponse::success((new SuperAdminUserResource($user))->resolve($request), 'User suspended and signed out everywhere.');
    }

    public function unsuspend(ReasonRequest $request, User $user)
    {
        $user = $this->users->unsuspend($request->user('super-admin'), $user, $request->validated('reason'));

        return ApiResponse::success((new SuperAdminUserResource($user))->resolve($request), 'User restored.');
    }

    public function resendVerification(ReasonRequest $request, User $user)
    {
        $this->users->resendVerification($request->user('super-admin'), $user, $request->validated('reason'));

        return ApiResponse::success(null, 'Verification email sent.');
    }

    public function forceLogout(ReasonRequest $request, User $user)
    {
        $this->users->forceLogout($request->user('super-admin'), $user, $request->validated('reason'));

        return ApiResponse::success(null, 'All sessions of this user were ended.');
    }
}
