<?php

namespace App\Http\Controllers\Api\Internal\V1\Library;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Library\ListLibraryRequest;
use App\Http\Resources\Api\Internal\V1\Library\LibraryItemResource;
use App\Http\Responses\ApiResponse;
use App\Models\Artifacts\Artifact;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Library: a gallery of the files that passed through agent chats
 * (what members uploaded and what agents made) and what members sent the
 * workflow builder, newest first. A read-only
 * view, separate from the Artifacts file manager (which owns uploading,
 * sharing and versions); both read the same stored files, since a chat file
 * is stored once, as an artifact.
 *
 * Chats are visible to anyone who may view agents, so that is the gate here;
 * each file's own artifact access still applies on top.
 */
class LibraryController extends Controller
{
    private const int DEFAULT_PER_PAGE = 48;

    public function index(ListLibraryRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentView);

        $user = $request->user();

        $items = Artifact::query()
            ->where('workspace_id', $workspace->id)
            ->where(fn ($query) => $query->whereNotNull('agent_session_id')->orWhereNotNull('workflow_builder_message_id'))
            ->latestPerGroup()
            ->accessibleBy($user, $user->can(Permission::ArtifactManage->value))
            ->with(['agent', 'agentSession', 'agentMessage'])
            ->when($request->validated('type'), fn ($query, string $type) => $type === 'image'
                ? $query->where('mime_type', 'like', 'image/%')
                : $query->where('mime_type', 'not like', 'image/%'))
            ->when($request->validated('agent_id'), fn ($query, string $agentId) => $query->where('agent_id', $agentId))
            ->when($request->validated('search'), fn ($query, string $search) => $query->where('filename', 'like', "%{$search}%"))
            ->orderByDesc('artifacts.created_at')
            ->paginate((int) ($request->validated('per_page') ?? self::DEFAULT_PER_PAGE));

        return ApiResponse::paginated(LibraryItemResource::collection($items));
    }

    public function download(Request $request, Workspace $workspace, Artifact $artifact): StreamedResponse
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureInLibrary($request, $workspace, $artifact);

        return Storage::disk($artifact->disk)->download($artifact->path, $artifact->filename, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Serves a file inline for the gallery's thumbnails and viewer. Reached
     * through a short-lived signed URL from `LibraryItemResource`, so an
     * `<img>` or `<iframe>` can load it without the API token.
     */
    public function view(Request $request, Artifact $artifact): StreamedResponse
    {
        abort_unless($artifact->isPreviewable(), 404);

        return Storage::disk($artifact->disk)->response($artifact->path, $artifact->filename, [
            'Content-Type' => $artifact->mime_type,
            // Same reasoning as `ArtifactController::preview()`: member- and
            // model-supplied bytes from this origin must not run as script.
            'Content-Security-Policy' => 'sandbox',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function ensureInLibrary(Request $request, Workspace $workspace, Artifact $artifact): void
    {
        $user = $request->user();

        abort_unless(
            $artifact->workspace_id === $workspace->id
                && ($artifact->agent_session_id !== null || $artifact->workflow_builder_message_id !== null)
                && $artifact->isAccessibleBy($user, $user->can(Permission::ArtifactManage->value)),
            404,
        );
    }
}
