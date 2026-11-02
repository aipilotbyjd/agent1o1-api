<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Intelligence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Intelligence\Experiment;
use App\Services\Intelligence\Experiments;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Creating, starting, stopping and reading A/B tests.
 */
class ExperimentController extends Controller
{
    public function __construct(
        private readonly Experiments $experiments,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    public function index()
    {
        return ApiResponse::success(Experiment::query()->latest()->get()->map(fn (Experiment $experiment): array => [
            ...$experiment->toArray(),
            'results' => $this->experiments->results($experiment),
        ]));
    }

    public function store(ReasonRequest $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:experiments,key'],
            'name' => ['required', 'string', 'max:200'],
            'hypothesis' => ['nullable', 'string', 'max:2000'],
            'variants' => ['required', 'array', 'min:2', 'max:6'],
            'variants.*.key' => ['required', 'string', 'max:64', 'alpha_dash', 'distinct'],
            'variants.*.weight' => ['required', 'integer', 'min:0', 'max:100'],
            'variants.*.settings' => ['nullable', 'array'],
            'guard_metric' => ['nullable', 'in:gross_margin'],
            'guard_threshold' => ['nullable', 'numeric'],
        ]);

        try {
            $experiment = $this->experiments->create($data);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $this->audit->record($request->user('super-admin'), 'intelligence.experiment.created', $experiment, null, $experiment->toArray(), $request->validated('reason'));

        return ApiResponse::success($experiment, 'Experiment created.', 201);
    }

    public function start(ReasonRequest $request, Experiment $experiment)
    {
        $this->audit->record($request->user('super-admin'), 'intelligence.experiment.started', $experiment, null, null, $request->validated('reason'));

        return ApiResponse::success($this->experiments->start($experiment), 'Experiment started.');
    }

    public function stop(ReasonRequest $request, Experiment $experiment)
    {
        $this->audit->record($request->user('super-admin'), 'intelligence.experiment.stopped', $experiment, null, null, $request->validated('reason'));

        return ApiResponse::success($this->experiments->stop($experiment, $request->validated('reason')), 'Experiment stopped.');
    }

    public function results(Request $request, Experiment $experiment)
    {
        return ApiResponse::success(['experiment' => $experiment, 'results' => $this->experiments->results($experiment)]);
    }
}
