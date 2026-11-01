<?php

use App\Actions\Workflows\CancelRunAction;
use App\Actions\Workflows\RetryRunAction;
use App\Enums\RunStatus;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Runs\Run;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    $this->admin = superAdmin(SuperAdminRole::Support);
    $this->headers = superAdminHeaders($this->admin);
    $this->workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
});

function saRunIn(Workspace $workspace, RunStatus $status, array $attributes = []): Run
{
    return Run::factory()->forWorkspace($workspace)->create(['status' => $status, ...$attributes]);
}

it('lists runs across workspaces with filters, without their data', function () {
    saRunIn($this->workspace, RunStatus::Completed, ['input' => ['secret' => 'customer-data']]);
    saRunIn($this->workspace, RunStatus::Failed, ['error' => 'Connector timed out']);

    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Globex']);
    saRunIn($other, RunStatus::Failed, ['error' => 'Bad credentials']);

    $this->withHeaders($this->headers)->getJson(saUrl('runs'))->assertOk()->assertJsonCount(3, 'data');
    $this->withHeaders($this->headers)->getJson(saUrl('runs?status=failed'))->assertJsonCount(2, 'data');
    $this->withHeaders($this->headers)->getJson(saUrl("runs?workspace_id={$other->id}"))->assertJsonCount(1, 'data');
    $this->withHeaders($this->headers)->getJson(saUrl('runs?with_error=1'))->assertJsonCount(2, 'data');
    $this->withHeaders($this->headers)->getJson(saUrl('runs?status=exploded'))->assertUnprocessable();

    $payload = $this->withHeaders($this->headers)->getJson(saUrl('runs'))->getContent();

    expect($payload)->not->toContain('customer-data');
});

it('shows one run and its steps, recording the view', function () {
    $run = saRunIn($this->workspace, RunStatus::Failed, ['error' => 'Connector timed out']);

    $this->withHeaders($this->headers)->getJson(saUrl("runs/{$run->id}"))
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.error', 'Connector timed out')
        ->assertJsonPath('data.workspace.name', 'Acme')
        ->assertJsonMissingPath('data.input')
        ->assertJsonMissingPath('data.output');

    $this->withHeaders($this->headers)->getJson(saUrl("runs/{$run->id}/node-runs"))->assertOk()->assertJsonCount(0, 'data');

    expect(SuperAdminAuditLog::query()->where('action', 'runs.viewed')->exists())->toBeTrue();
});

it('summarises the most common failures', function () {
    foreach (range(1, 3) as $i) {
        saRunIn($this->workspace, RunStatus::Failed, ['error' => 'Connector timed out']);
    }
    saRunIn($this->workspace, RunStatus::Failed, ['error' => 'Bad credentials']);
    saRunIn($this->workspace, RunStatus::Completed);

    $this->withHeaders($this->headers)->getJson(saUrl('runs/failures/summary'))
        ->assertOk()
        ->assertJsonPath('data.errors.0.message', 'Connector timed out')
        ->assertJsonPath('data.errors.0.total', 3)
        ->assertJsonCount(2, 'data.errors');
});

it('refuses to cancel a finished run and to retry one still in flight', function () {
    $finished = saRunIn($this->workspace, RunStatus::Completed);
    $running = saRunIn($this->workspace, RunStatus::Running);

    $this->withHeaders($this->headers)->postJson(saUrl("runs/{$finished->id}/cancel"), ['reason' => 'Stuck for hours'])->assertStatus(409);
    $this->withHeaders($this->headers)->postJson(saUrl("runs/{$running->id}/retry"), ['reason' => 'Looks stuck to me'])->assertStatus(409);
});

it('retries and cancels through the product actions, recording why', function () {
    $failed = saRunIn($this->workspace, RunStatus::Failed);
    $newRun = saRunIn($this->workspace, RunStatus::Running);
    $cancelled = saRunIn($this->workspace, RunStatus::Cancelled);

    $this->mock(RetryRunAction::class, fn ($mock) => $mock->shouldReceive('execute')->once()->andReturn($newRun));
    $this->mock(CancelRunAction::class, fn ($mock) => $mock->shouldReceive('execute')->once()->andReturn($cancelled));

    $this->withHeaders($this->headers)->postJson(saUrl("runs/{$failed->id}/retry"), ['reason' => 'Upstream outage is over'])
        ->assertCreated()->assertJsonPath('data.id', $newRun->id);

    $this->withHeaders($this->headers)->postJson(saUrl("runs/{$newRun->id}/cancel"), ['reason' => 'Customer asked us to stop it'])
        ->assertOk()->assertJsonPath('data.status', 'cancelled');

    $retried = SuperAdminAuditLog::query()->where('action', 'runs.retried')->firstOrFail();

    expect($retried->after)->toBe(['new_run_id' => $newRun->id])
        ->and($retried->reason)->toBe('Upstream outage is over')
        ->and(SuperAdminAuditLog::query()->where('action', 'runs.cancelled')->exists())->toBeTrue();
});

it('needs a reason, and the retry and cancel abilities', function () {
    $run = saRunIn($this->workspace, RunStatus::Failed);

    $this->withHeaders($this->headers)->postJson(saUrl("runs/{$run->id}/retry"))->assertUnprocessable()->assertJsonValidationErrors('reason');

    foreach ([SuperAdminRole::Finance, SuperAdminRole::Viewer] as $role) {
        $this->withHeaders(superAdminHeaders(superAdmin($role)))
            ->postJson(saUrl("runs/{$run->id}/retry"), ['reason' => 'Not allowed to do this'])
            ->assertForbidden();
    }
});
