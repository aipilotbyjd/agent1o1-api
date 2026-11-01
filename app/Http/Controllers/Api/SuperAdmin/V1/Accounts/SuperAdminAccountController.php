<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Accounts;

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Accounts\ChangeRoleRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Accounts\StoreAccountRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Accounts\UpdateAccountRequest;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Accounts\SuperAdminAccountResource;
use App\Http\Resources\Api\SuperAdmin\V1\Auth\SuperAdminLoginEventResource;
use App\Http\Responses\ApiResponse;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Accounts\SuperAdminAccountService;
use Illuminate\Http\Request;

class SuperAdminAccountController extends Controller
{
    public function __construct(private readonly SuperAdminAccountService $accounts) {}

    public function index(Request $request)
    {
        $accounts = SuperAdmin::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';

                $query->where(fn ($inner) => $inner->whereLike('email', $term)->orWhereLike('name', $term));
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->when($request->boolean('disabled'), fn ($query) => $query->whereNotNull('disabled_at'))
            ->orderBy('created_at')
            ->paginate(min($request->integer('per_page', 25), 100));

        return ApiResponse::paginated(SuperAdminAccountResource::collection($accounts));
    }

    public function show(SuperAdmin $account)
    {
        return ApiResponse::success((new SuperAdminAccountResource($account))->resolve());
    }

    public function store(StoreAccountRequest $request)
    {
        $result = $this->accounts->create(
            $request->user('super-admin'),
            [
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'role' => SuperAdminRole::from($request->validated('role')),
                'allowed_ips' => $request->validated('allowed_ips'),
            ],
            $request->validated('reason'),
        );

        return ApiResponse::created([
            'admin' => (new SuperAdminAccountResource($result['admin']))->resolve(),
            // Shown once. The account must change it at first sign-in.
            'temporary_password' => $result['temporary_password'],
        ], 'Account created. Share the temporary password through a secure channel.');
    }

    public function update(UpdateAccountRequest $request, SuperAdmin $account)
    {
        $account = $this->accounts->update($request->user('super-admin'), $account, $request->validated());

        return ApiResponse::success((new SuperAdminAccountResource($account))->resolve(), 'Account updated.');
    }

    public function changeRole(ChangeRoleRequest $request, SuperAdmin $account)
    {
        $account = $this->accounts->changeRole(
            $request->user('super-admin'),
            $account,
            SuperAdminRole::from($request->validated('role')),
            $request->validated('reason'),
        );

        return ApiResponse::success((new SuperAdminAccountResource($account))->resolve(), 'Role updated.');
    }

    public function disable(StepUpRequest $request, SuperAdmin $account)
    {
        $account = $this->accounts->disable($request->user('super-admin'), $account, $request->validated('reason'));

        return ApiResponse::success((new SuperAdminAccountResource($account))->resolve(), 'Account disabled.');
    }

    public function enable(StepUpRequest $request, SuperAdmin $account)
    {
        $account = $this->accounts->enable($request->user('super-admin'), $account, $request->validated('reason'));

        return ApiResponse::success((new SuperAdminAccountResource($account))->resolve(), 'Account enabled.');
    }

    public function resetTwoFactor(StepUpRequest $request, SuperAdmin $account)
    {
        $account = $this->accounts->resetTwoFactor($request->user('super-admin'), $account, $request->validated('reason'));

        return ApiResponse::success((new SuperAdminAccountResource($account))->resolve(), 'Two-factor authentication reset. The account was signed out everywhere.');
    }

    public function revokeSessions(StepUpRequest $request, SuperAdmin $account)
    {
        $account = $this->accounts->revokeSessions($request->user('super-admin'), $account, $request->validated('reason'));

        return ApiResponse::success((new SuperAdminAccountResource($account))->resolve(), 'Sessions revoked.');
    }

    public function loginEvents(Request $request, SuperAdmin $account)
    {
        $events = $account->loginEvents()->latest('created_at')->limit(100)->get();

        return ApiResponse::success(SuperAdminLoginEventResource::collection($events)->resolve($request));
    }
}
