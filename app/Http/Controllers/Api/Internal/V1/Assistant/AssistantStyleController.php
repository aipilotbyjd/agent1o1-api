<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Enums\Assistant\AssistantStyleKind;
use App\Enums\Assistant\AssistantStyleSource;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Assistant\Concerns\ResolvesOwnAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Assistant\UpdateAssistantStyleRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Assistant\AssistantStyleProfile;
use App\Models\Assistant\AssistantStyleRevision;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\Personalization\StyleProfiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Personalization: the owner's Tone and Design notes, their history, and
 * restoring an earlier version.
 */
class AssistantStyleController extends Controller
{
    use ResolvesOwnAssistant;

    private const int REVISIONS_SHOWN = 20;

    public function __construct(private StyleProfiles $profiles) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $assistant = $this->ownAssistant($request, $workspace);

        return ApiResponse::success([
            'styles' => collect(AssistantStyleKind::cases())
                ->map(fn (AssistantStyleKind $kind): array => $this->describe($this->profiles->profile($assistant, $kind)))
                ->values(),
        ]);
    }

    public function update(UpdateAssistantStyleRequest $request, Workspace $workspace, string $kind): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $profile = $this->profiles->update(
            $this->ownAssistant($request, $workspace),
            $this->kind($kind),
            $request->validated('notes'),
            AssistantStyleSource::Owner,
            'Edited in Personalization',
        );

        return ApiResponse::success(['style' => $this->describe($profile)], 'Saved.');
    }

    public function restore(Request $request, Workspace $workspace, string $kind, AssistantStyleRevision $revision): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $assistant = $this->ownAssistant($request, $workspace);
        $profile = $revision->profile;

        abort_if($profile->assistant_id !== $assistant->id || $profile->kind !== $this->kind($kind), 404);

        return ApiResponse::success(['style' => $this->describe($this->profiles->restore($revision))], 'Restored.');
    }

    private function kind(string $kind): AssistantStyleKind
    {
        return AssistantStyleKind::tryFrom($kind) ?? abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(AssistantStyleProfile $profile): array
    {
        return [
            'kind' => $profile->kind->value,
            'notes' => $profile->body,
            'version' => $profile->version,
            'revisions' => $profile->revisions()
                ->latest('version')
                ->limit(self::REVISIONS_SHOWN)
                ->get()
                ->map(fn (AssistantStyleRevision $revision): array => [
                    'id' => $revision->id,
                    'version' => $revision->version,
                    'notes' => $revision->body,
                    'source' => $revision->source->value,
                    'reason' => $revision->reason,
                    'created_at' => $revision->created_at,
                ])
                ->values(),
        ];
    }
}
