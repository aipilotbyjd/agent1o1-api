<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\GitSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class GitSyncController extends Controller
{
    public function __construct(private readonly GitSyncService $gitSync) {}

    public function show(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkspaceUpdate)) {
            return $denied;
        }

        $config = $workspace->gitSyncConfig;

        return $this->successResponse('Git sync config retrieved.', $config?->toArray());
    }

    public function configure(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkspaceUpdate)) {
            return $denied;
        }

        $data = $request->validate([
            'provider' => ['nullable', 'in:github'],
            'repository' => ['required', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'base_path' => ['nullable', 'string', 'max:255'],
            'access_token' => ['required', 'string'],
        ]);

        $config = $workspace->gitSyncConfig()->updateOrCreate([], [
            'created_by' => $request->user()->id,
            'provider' => $data['provider'] ?? 'github',
            'repository' => $data['repository'],
            'branch' => $data['branch'] ?? 'main',
            'base_path' => $data['base_path'] ?? 'workflows',
            'access_token' => $data['access_token'],
            'webhook_secret' => Str::random(40),
            'is_active' => true,
        ]);

        return $this->successResponse('Git sync configured.', $config->toArray(), 201);
    }

    public function export(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkspaceUpdate)) {
            return $denied;
        }

        $config = $workspace->gitSyncConfig;

        if (! $config) {
            return $this->errorResponse('Git sync is not configured.', 422);
        }

        try {
            $result = $this->gitSync->export($config);
        } catch (Throwable $e) {
            return $this->errorResponse("Export failed: {$e->getMessage()}", 422);
        }

        return $this->successResponse('Workflows exported to Git.', $result);
    }

    public function import(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkspaceUpdate)) {
            return $denied;
        }

        $config = $workspace->gitSyncConfig;

        if (! $config) {
            return $this->errorResponse('Git sync is not configured.', 422);
        }

        try {
            $imported = $this->gitSync->import($config, $workspace);
        } catch (Throwable $e) {
            return $this->errorResponse("Import failed: {$e->getMessage()}", 422);
        }

        return $this->successResponse('Workflows imported from Git.', ['imported' => $imported]);
    }
}
