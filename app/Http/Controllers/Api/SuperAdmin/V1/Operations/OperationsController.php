<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Operations;

use App\Enums\Operations\TaskEntryType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SuperAdmin\V1\Operations\OperationsTaskEntryResource;
use App\Http\Resources\Api\SuperAdmin\V1\Operations\TaskStateResource;
use App\Http\Responses\ApiResponse;
use App\Models\Operations\OperationsTaskEntry;
use App\Services\SuperAdmin\Operations\OperationsBoard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The checklist as the panel shows it: every task with where it stands, the
 * counts for the header badge, and the history of what was done.
 */
class OperationsController extends Controller
{
    public function __construct(private readonly OperationsBoard $board) {}

    public function index(Request $request)
    {
        $states = $this->board->states();

        return ApiResponse::success([
            'summary' => $this->board->summary($request->user('super-admin'), $states),
            'tasks' => TaskStateResource::collection($states)->resolve($request),
        ]);
    }

    public function summary(Request $request)
    {
        return ApiResponse::success($this->board->summary($request->user('super-admin')));
    }

    public function history(Request $request)
    {
        $request->validate([
            'type' => ['nullable', Rule::enum(TaskEntryType::class)],
            'task_id' => ['nullable', 'uuid'],
        ]);

        $entries = OperationsTaskEntry::query()
            ->with(['task:id,title,cadence', 'actor:id,name'])
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('task_id'), fn ($query) => $query->where('operations_task_id', $request->string('task_id')->toString()))
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated(OperationsTaskEntryResource::collection($entries));
    }
}
