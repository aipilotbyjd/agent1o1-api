<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Workspaces\SuperAdminWorkspaceResource;
use App\Http\Responses\ApiResponse;
use App\Models\SuperAdmin\SuperAdminAccountTag;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use App\Services\SuperAdmin\Customers\CustomerAnnotations;
use App\Services\SuperAdmin\Customers\CustomerSubjects;
use App\Services\SuperAdmin\Workspaces\SuperAdminWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuperAdminWorkspaceController extends Controller
{
    public function __construct(
        private readonly SuperAdminWorkspaceService $workspaces,
        private readonly SuperAdminAuditLogger $audit,
        private readonly CustomerAnnotations $annotations,
    ) {}

    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', Rule::in(['active', 'suspended'])], 'tag' => ['nullable', 'string', 'max:24']]);

        $workspaces = Workspace::query()
            ->with('owner:id,name,email')
            ->withCount('members')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';

                $query->where(fn ($inner) => $inner->whereLike('name', $term)->orWhereLike('slug', $term));
            })
            ->when($request->filled('tag'), fn ($query) => $query->whereIn('id', SuperAdminAccountTag::query()->where('subject_type', CustomerSubjects::WORKSPACE)->where('tag', mb_strtolower($request->string('tag')->toString()))->select('subject_id')))
            ->when($request->string('status')->toString() === 'suspended', fn ($query) => $query->whereNotNull('suspended_at'))
            ->when($request->string('status')->toString() === 'active', fn ($query) => $query->whereNull('suspended_at'))
            ->latest()
            ->orderBy('id')
            ->paginate(min($request->integer('per_page', 25), 100));

        $this->annotations->attach(CustomerSubjects::WORKSPACE, $workspaces->getCollection());

        return ApiResponse::paginated(SuperAdminWorkspaceResource::collection($workspaces));
    }

    public function show(Request $request, Workspace $workspace)
    {
        $workspace->load('owner:id,name,email')->loadCount('members');
        $this->annotations->attach(CustomerSubjects::WORKSPACE, [$workspace]);

        $this->audit->record($request->user('super-admin'), 'workspaces.viewed', $workspace);

        return ApiResponse::success((new SuperAdminWorkspaceResource($workspace))->resolve($request));
    }

    public function members(Request $request, Workspace $workspace)
    {
        $members = $workspace->members()->with('user:id,name,email,suspended_at')->orderBy('created_at')->get();

        $this->audit->record($request->user('super-admin'), 'workspaces.members_viewed', $workspace);

        return ApiResponse::success($members->map(fn (WorkspaceMember $member): array => [
            'id' => $member->id,
            'role' => $member->role->value,
            'joined_at' => $member->joined_at,
            'user' => [
                'id' => $member->user->id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'suspended_at' => $member->user->suspended_at,
            ],
        ])->all());
    }

    public function suspend(ReasonRequest $request, Workspace $workspace)
    {
        $workspace = $this->workspaces->suspend($request->user('super-admin'), $workspace, $request->validated('reason'));

        return ApiResponse::success((new SuperAdminWorkspaceResource($workspace))->resolve($request), 'Workspace suspended.');
    }

    public function unsuspend(ReasonRequest $request, Workspace $workspace)
    {
        $workspace = $this->workspaces->unsuspend($request->user('super-admin'), $workspace, $request->validated('reason'));

        return ApiResponse::success((new SuperAdminWorkspaceResource($workspace))->resolve($request), 'Workspace restored.');
    }

    public function removeMember(StepUpRequest $request, Workspace $workspace, string $member)
    {
        $this->workspaces->removeMember(
            $request->user('super-admin'),
            $workspace,
            $workspace->members()->whereKey($member)->firstOrFail(),
            $request->validated('reason'),
        );

        return ApiResponse::noContent();
    }
}
