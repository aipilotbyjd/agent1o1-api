<?php

namespace App\Http\Controllers\Api\Internal\V1\Artifacts;

use App\Actions\Artifacts\StoreArtifactAction;
use App\Enums\Artifacts\ArtifactGeneralAccess;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Artifacts\ShareArtifactRequest;
use App\Http\Requests\Api\Internal\V1\Artifacts\UpdateArtifactAccessRequest;
use App\Http\Requests\Api\Internal\V1\Artifacts\UploadArtifactRequest;
use App\Http\Resources\Api\Internal\V1\Artifacts\ArtifactResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Artifacts\Artifact;
use App\Models\Artifacts\ArtifactShare;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArtifactController extends Controller
{
    private const MIME_CATEGORIES = [
        'images' => ['image/'],
        'documents' => ['text/html', 'application/pdf'],
        'spreadsheets' => ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml'],
    ];

    public function index(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::ArtifactView);

        $user = $request->user();
        $canManage = $user->can(Permission::ArtifactManage->value);

        $artifacts = Artifact::query()
            ->where('workspace_id', $workspace->id)
            ->latestPerGroup()
            ->with(['agent', 'creator'])
            ->withCount('versions')
            ->when(! $canManage, fn ($q) => $q->where(function ($access) use ($user) {
                $access->where('general_access', '!=', ArtifactGeneralAccess::Restricted->value)
                    ->orWhere('created_by', $user->id)
                    ->orWhereHas('shares', fn ($shared) => $shared->where('user_id', $user->id));
            }))
            ->when($request->query('search'), fn ($q, $search) => $q->where('filename', 'like', "%{$search}%"))
            ->when($request->query('agent_id'), fn ($q, $agentId) => $q->where('agent_id', $agentId))
            ->when($request->query('mime_category'), function ($q, $category) {
                $prefixes = self::MIME_CATEGORIES[$category] ?? null;

                if (! $prefixes) {
                    return $q;
                }

                return $q->where(function ($sub) use ($prefixes) {
                    foreach ($prefixes as $prefix) {
                        $sub->orWhere('mime_type', 'like', "{$prefix}%");
                    }
                });
            })
            ->orderByDesc('artifacts.created_at')
            ->paginate(max(1, min((int) $request->query('per_page', 25), 100)));

        return ApiResponse::paginated(ArtifactResource::collection($artifacts));
    }

    public function store(UploadArtifactRequest $request, Workspace $workspace, StoreArtifactAction $storeArtifact)
    {
        $this->requirePermission(Permission::ArtifactManage);

        $file = $request->file('file');
        $agentId = $request->validated('agent_id');

        $artifact = $storeArtifact->execute(
            workspace: $workspace,
            filename: $request->validated('filename') ?? $file->getClientOriginalName(),
            // Guessed from the file's own bytes, not the client-supplied
            // Content-Type header: the stored value is what `preview()`
            // later serves the file as.
            mimeType: $file->getMimeType() ?? 'application/octet-stream',
            contents: $file,
            agent: $agentId === null ? null : Agent::findOrFail($agentId),
            createdBy: $request->user()->id,
            groupId: $request->validated('group_id'),
            metadata: $request->validated('metadata'),
        );

        return ApiResponse::created([
            'artifact' => ArtifactResource::make($artifact->load(['agent', 'creator'])),
        ], 'Artifact uploaded.');
    }

    public function show(Request $request, Workspace $workspace, Artifact $artifact)
    {
        $this->requirePermission(Permission::ArtifactView);
        $this->ensureBelongsToWorkspace($workspace, $artifact);
        $this->ensureAccessible($request, $artifact);

        return ApiResponse::success([
            'artifact' => ArtifactResource::make($artifact->load(['agent', 'creator', 'versions'])),
        ]);
    }

    public function destroy(Workspace $workspace, Artifact $artifact)
    {
        $this->requirePermission(Permission::ArtifactManage);
        $this->ensureBelongsToWorkspace($workspace, $artifact);

        // Soft delete: storage and every version's row survive, so a later
        // re-export with this group's filename restores the group and
        // continues its version history: see `StoreArtifactAction`.
        Artifact::where('group_id', $artifact->group_id)->delete();

        return ApiResponse::noContent();
    }

    public function download(Request $request, Workspace $workspace, Artifact $artifact): StreamedResponse
    {
        $this->requirePermission(Permission::ArtifactView);
        $this->ensureBelongsToWorkspace($workspace, $artifact);
        $this->ensureAccessible($request, $artifact);

        return Storage::disk($artifact->disk)->download($artifact->path, $artifact->filename, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Sets the group's sharing tier: General Access in Gumloop's terms.
     */
    public function updateAccess(UpdateArtifactAccessRequest $request, Workspace $workspace, Artifact $artifact)
    {
        $this->requirePermission(Permission::ArtifactView);
        $this->ensureBelongsToWorkspace($workspace, $artifact);
        $this->ensureCanShare($request, $artifact);

        Artifact::where('group_id', $artifact->group_id)
            ->update(['general_access' => $request->validated('general_access')]);

        return ApiResponse::success([
            'artifact' => ArtifactResource::make($artifact->refresh()->load(['agent', 'creator'])),
        ]);
    }

    /**
     * Grants one workspace member explicit access to a `restricted` artifact.
     */
    public function addShare(ShareArtifactRequest $request, Workspace $workspace, Artifact $artifact)
    {
        $this->requirePermission(Permission::ArtifactView);
        $this->ensureBelongsToWorkspace($workspace, $artifact);
        $this->ensureCanShare($request, $artifact);

        ArtifactShare::query()->firstOrCreate(
            ['group_id' => $artifact->group_id, 'user_id' => $request->validated('user_id')],
            ['granted_by' => $request->user()->id],
        );

        return ApiResponse::success([
            'artifact' => ArtifactResource::make($artifact->load(['agent', 'creator', 'shares.user'])),
        ]);
    }

    public function removeShare(Request $request, Workspace $workspace, Artifact $artifact, User $user)
    {
        $this->requirePermission(Permission::ArtifactView);
        $this->ensureBelongsToWorkspace($workspace, $artifact);
        $this->ensureCanShare($request, $artifact);

        ArtifactShare::query()
            ->where('group_id', $artifact->group_id)
            ->where('user_id', $user->id)
            ->delete();

        return ApiResponse::noContent();
    }

    private function ensureAccessible(Request $request, Artifact $artifact): void
    {
        $user = $request->user();

        abort_unless(
            $artifact->isAccessibleBy($user, $user->can(Permission::ArtifactManage->value)),
            403,
            'You do not have access to this artifact.',
        );
    }

    /**
     * Only the creator or someone with `artifact.manage` may change an
     * artifact's sharing: narrower than `artifact.view`, which everyone
     * calling this controller already has.
     */
    private function ensureCanShare(Request $request, Artifact $artifact): void
    {
        $user = $request->user();

        abort_unless(
            $artifact->created_by === $user->id || $user->can(Permission::ArtifactManage->value),
            403,
            'Only the creator or a manager may change this artifact\'s sharing.',
        );
    }

    public function preview(Request $request, Artifact $artifact): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired signature.');
        abort_unless($artifact->isPreviewable(), 404);

        return Storage::disk($artifact->disk)->response($artifact->path, $artifact->filename, [
            'Content-Type' => $artifact->mime_type,
            // Artifacts are member- and model-supplied bytes served from this
            // application's own origin: an uploaded `text/html` artifact would
            // otherwise run as first-party script. The sandbox denies it
            // everything (scripts, forms, same-origin access) while still
            // rendering, and nosniff keeps the browser from re-deciding the
            // type for itself.
            'Content-Security-Policy' => 'sandbox',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
