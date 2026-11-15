<?php

use App\Enums\Notifications\AlertSeverity;
use App\Enums\Operations\TaskKind;
use App\Models\Operations\OperationsTask;
use App\Models\Operations\OperationsTaskEntry;
use App\Services\Notifications\AdminAlerts;
use App\Services\SuperAdmin\Operations\OperationsChecks;
use App\Services\SuperAdmin\Operations\TaskCatalog;

it('starts a fresh install with the built-in checklist', function () {
    expect(OperationsTask::query()->count())->toBe(count(app(TaskCatalog::class)->definitions()));
});

it('has a unique key for every built-in task, and every automatic one points to a real check', function () {
    $definitions = collect(app(TaskCatalog::class)->definitions());

    expect($definitions->pluck('key')->duplicates())->toBeEmpty();

    $known = app(OperationsChecks::class)->keys();

    $definitions->where('kind', TaskKind::Auto)->each(fn (array $task) => expect($known)->toContain($task['check']));
    $definitions->each(fn (array $task) => expect($task['target'] ?? null)->toBeIn([null, ...config('operations.targets')]));
});

it('adds only the tasks that are missing, and leaves edited ones alone', function () {
    $task = OperationsTask::query()->where('key', 'daily.dashboard')->firstOrFail();
    $task->update(['title' => 'My own words', 'enabled' => false]);
    OperationsTask::query()->where('key', 'weekly.audit')->delete();

    $this->artisan('operations:sync-tasks')->expectsOutput('Added 1 checklist task(s).')->assertSuccessful();

    expect($task->fresh()->title)->toBe('My own words')->and($task->fresh()->enabled)->toBeFalse();
    expect(OperationsTask::query()->where('key', 'weekly.audit')->exists())->toBeTrue();

    $this->artisan('operations:sync-tasks')->expectsOutput('The checklist is up to date.')->assertSuccessful();
});

/**
 * Replaces the alert sender with one that remembers what it was asked to send.
 */
function fakeDigestAlerts(): AdminAlerts
{
    $fake = new class extends AdminAlerts
    {
        /** @var list<array{key: string, title: string, body: ?string, severity: AlertSeverity}> */
        public array $raised = [];

        public function raise(string $key, string $title, ?string $body = null, array $context = [], AlertSeverity $severity = AlertSeverity::Warning, ?string $throttleKey = null): bool
        {
            $this->raised[] = ['key' => $key, 'title' => $title, 'body' => $body, 'severity' => $severity];

            return true;
        }
    };

    app()->instance(AdminAlerts::class, $fake);

    return $fake;
}

it('sends nothing in the morning when everything is done', function () {
    $alerts = fakeDigestAlerts();
    OperationsTask::query()->delete();
    $task = OperationsTask::factory()->create();
    OperationsTaskEntry::factory()->create(['operations_task_id' => $task->id]);

    $this->artisan('operations:digest')->expectsOutput('Nothing on the checklist needs attention.')->assertSuccessful();

    expect($alerts->raised)->toBe([]);
});

it('reminds you in the morning of what needs attention, loudest when something is overdue', function () {
    $alerts = fakeDigestAlerts();
    OperationsTask::query()->delete();
    OperationsTask::factory()->create(['title' => 'Glance at the dashboard', 'created_at' => now()->subDay()]);

    $this->artisan('operations:digest')->assertSuccessful();

    expect($alerts->raised)->toHaveCount(1)
        ->and($alerts->raised[0]['key'])->toBe('operations.daily_digest')
        ->and($alerts->raised[0]['body'])->toContain('[Due] Glance at the dashboard')
        ->and($alerts->raised[0]['severity'])->toBe(AlertSeverity::Info);

    $this->travelTo(today()->setTime(13, 0));
    $this->artisan('operations:digest')->assertSuccessful();

    expect($alerts->raised[1]['severity'])->toBe(AlertSeverity::Warning)->and($alerts->raised[1]['body'])->toContain('[Overdue]');
});

it('schedules the morning reminder', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('operations:digest')->assertSuccessful();
});
