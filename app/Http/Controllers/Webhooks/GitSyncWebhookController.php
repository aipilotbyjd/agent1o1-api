<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\GitSyncConfig;
use App\Services\GitSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitSyncWebhookController extends Controller
{
    public function __construct(private readonly GitSyncService $gitSync) {}

    /**
     * Public endpoint hit by the Git provider on push; pulls the latest workflows.
     */
    public function receive(Request $request, string $config): JsonResponse
    {
        $gitConfig = GitSyncConfig::where('id', $config)->where('is_active', true)->first();

        if (! $gitConfig) {
            return $this->errorResponse('Git sync config not found.', 404);
        }

        if ($gitConfig->webhook_secret && ! hash_equals($gitConfig->webhook_secret, (string) $request->header('X-Sync-Secret'))) {
            return $this->errorResponse('Invalid webhook signature.', 403);
        }

        $imported = $this->gitSync->import($gitConfig, $gitConfig->workspace);

        return $this->successResponse('Sync received.', ['imported' => $imported]);
    }
}
