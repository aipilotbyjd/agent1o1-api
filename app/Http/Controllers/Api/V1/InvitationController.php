<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workspace\StoreInvitationRequest;
use App\Http\Resources\V1\InvitationResource;
use App\Models\Invitation;
use App\Models\Workspace;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function __construct(private InvitationService $invitationService) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::MemberView)) {
            return $forbidden;
        }

        $invitations = $workspace->invitations()
            ->pending()
            ->with('inviter')
            ->paginate(50);

        return $this->paginatedResponse(
            'Invitations retrieved.',
            InvitationResource::collection($invitations),
        );
    }

    public function store(StoreInvitationRequest $request, Workspace $workspace): JsonResponse
    {
        $invitation = $this->invitationService->send($workspace, $request->user(), $request->validated());

        return $this->successResponse(
            'Invitation sent.',
            new InvitationResource($invitation->load('inviter')),
            201,
        );
    }

    public function destroy(Request $request, Workspace $workspace, Invitation $invitation): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::MemberInvite)) {
            return $forbidden;
        }

        $this->invitationService->revoke($invitation);

        return $this->successResponse('Invitation revoked.');
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = $this->invitationService->accept($token, $request->user());

        return $this->successResponse(
            'Invitation accepted.',
            new InvitationResource($invitation),
        );
    }

    public function decline(Request $request, string $token): JsonResponse
    {
        $invitation = $this->invitationService->decline($token, $request->user());

        return $this->successResponse(
            'Invitation declined.',
            new InvitationResource($invitation),
        );
    }
}
