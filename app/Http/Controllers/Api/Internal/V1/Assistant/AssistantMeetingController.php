<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Assistant\Concerns\ResolvesOwnAssistant;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantBriefingRun;
use App\Models\Assistant\AssistantMeeting;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\Meetings\MeetingPrepScheduler;
use App\Services\Assistant\Meetings\MeetingSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Upcoming meetings and their briefs. Listing refreshes the calendar (at
 * most every two minutes), so the list is current even with automatic prep
 * off; "Prepare now" works either way.
 */
class AssistantMeetingController extends Controller
{
    use ResolvesOwnAssistant;

    private const int SYNC_EVERY_SECONDS = 120;

    public function __construct(
        private MeetingSync $sync,
        private MeetingPrepScheduler $scheduler,
    ) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $assistant = $this->ownAssistant($request, $workspace);
        $connected = $this->sync->isConnected($assistant);

        if ($connected) {
            $this->refresh($assistant);
        }

        $meetings = $assistant->meetings()
            ->with('latestRun')
            ->where('starts_at', '>', now()->subHour())
            ->where('starts_at', '<=', now()->addDays((int) config('assistant.meetings.sync_days')))
            ->orderBy('starts_at')
            ->get();

        return ApiResponse::success([
            'calendar_connected' => $connected,
            'meetings' => $meetings->map(fn (AssistantMeeting $meeting): array => $this->describe($meeting))->values(),
        ]);
    }

    public function prepare(Request $request, Workspace $workspace, AssistantMeeting $meeting): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        abort_if($meeting->assistant_id !== $this->ownAssistant($request, $workspace)->id, 404);

        $this->scheduler->prepareNow($meeting);

        return ApiResponse::success(['meeting' => $this->describe($meeting->refresh()->load('latestRun'))], 'Preparing your brief.', Response::HTTP_ACCEPTED);
    }

    private function refresh(Assistant $assistant): void
    {
        try {
            Cache::remember("assistant:{$assistant->id}:meetings-synced", self::SYNC_EVERY_SECONDS, function () use ($assistant): bool {
                $this->sync->sync($assistant);

                return true;
            });
        } catch (Throwable $e) {
            // A calendar hiccup shows the meetings already known.
            report($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(AssistantMeeting $meeting): array
    {
        $run = $meeting->latestRun;

        return [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'starts_at' => $meeting->starts_at,
            'ends_at' => $meeting->ends_at,
            'attendees' => $meeting->attendees ?? [],
            'is_external' => $meeting->is_external,
            'html_link' => $meeting->html_link,
            'prep_status' => $meeting->prep_status->value,
            'brief' => $run instanceof AssistantBriefingRun ? [
                'run_id' => $run->id,
                'status' => $run->status->value,
                'summary' => $run->summary,
                'document' => $run->document,
                'error' => $run->error,
                'sources' => $run->source_results ?? [],
            ] : null,
        ];
    }
}
