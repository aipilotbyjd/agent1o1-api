<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ArtifactResource;
use App\Models\Artifact;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ArtifactController extends Controller
{
    private const MIME_CATEGORIES = [
        'images' => ['image/'],
        'documents' => ['text/html', 'application/pdf'],
        'spreadsheets' => ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml'],
    ];

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        $artifacts = Artifact::query()
            ->where('workspace_id', $workspace->id)
            ->latestPerGroup()
            ->with(['agent', 'creator'])
            ->withCount('versions')
            ->when($request->query('search'), fn ($q, $search) => $q->where('filename', 'ilike', "%{$search}%"))
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
            ->paginate((int) $request->query('per_page', 25));

        return $this->paginatedResponse('Artifacts retrieved.', ArtifactResource::collection($artifacts));
    }

    public function show(Request $request, Workspace $workspace, Artifact $artifact): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        return $this->successResponse(
            'Artifact retrieved.',
            new ArtifactResource($artifact->load(['agent', 'creator', 'versions'])),
        );
    }

    public function destroy(Request $request, Workspace $workspace, Artifact $artifact): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentDelete)) {
            return $denied;
        }

        $group = Artifact::where('group_id', $artifact->group_id)->get();

        foreach ($group as $version) {
            Storage::disk($version->disk)->delete($version->path);
            $version->delete();
        }

        return $this->successResponse('Artifact deleted.');
    }

    public function download(Request $request, Workspace $workspace, Artifact $artifact): Response
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            abort(403, 'Forbidden.');
        }

        return Storage::disk($artifact->disk)->download($artifact->path, $artifact->filename);
    }

    public function preview(Request $request, Artifact $artifact): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired signature.');
        }

        if (! $artifact->isPreviewable()) {
            abort(404);
        }

        return Storage::disk($artifact->disk)->response($artifact->path, $artifact->filename, [
            'Content-Type' => $artifact->mime_type,
        ]);
    }
}
