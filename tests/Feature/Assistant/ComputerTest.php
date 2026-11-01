<?php

use App\Actions\Artifacts\StoreArtifactAction;
use App\Ai\Assistant\Tools\ExportFileTool;
use App\Ai\Assistant\Tools\RunCodeTool;
use App\Enums\Billing\CreditTransactionType;
use App\Http\Resources\Api\Internal\V1\Assistant\AssistantMessageResource;
use App\Models\Artifacts\Artifact;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSandbox;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\User;
use App\Services\Artifacts\DocumentRenderer;
use App\Services\Assistant\Computer\ComputerToolProvider;
use App\Services\Assistant\Computer\Sandboxes;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    Storage::fake(config('artifacts.disk'));
    config(['assistant.sandbox.api_key' => 'e2b-key', 'assistant.sandbox.domain' => 'e2b.test', 'assistant.sandbox.api_url' => 'https://api.e2b.test']);

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->session = $this->assistant->sessions()->create(['last_activity_at' => now()]);

    $this->givePlan = fn () => PlanGrant::factory()->forWorkspace($this->workspace)
        ->forPlan(Plan::factory()->create(['features' => ['assistant_sandbox' => true]]))
        ->active()
        ->create();

    $this->tools = fn (): array => collect(app(ComputerToolProvider::class)->toolsFor($this->assistant, $this->session))->map->name()->all();

    $this->ndjson = fn (array $events): string => collect($events)->map(fn (array $event): string => json_encode($event))->implode("\n");
});

it('offers run_code only on plans with the computer, and export_file always', function () {
    expect(($this->tools)())->toBe([ExportFileTool::NAME]);

    ($this->givePlan)();

    expect(($this->tools)())->toBe([ExportFileTool::NAME, RunCodeTool::NAME]);

    $this->session->forceFill(['incognito' => true])->save();
    expect(($this->tools)())->toBe([]);
});

it('starts a computer once per conversation, runs code and bills by the minute', function () {
    ($this->givePlan)();
    Http::fake([
        'https://api.e2b.test/sandboxes' => Http::response(['sandboxID' => 'sbx1', 'envdAccessToken' => 'envd-token']),
        'https://api.e2b.test/sandboxes/sbx1/timeout' => Http::response([]),
        'https://49999-sbx1.e2b.test/execute' => Http::response(($this->ndjson)([
            ['type' => 'stdout', 'text' => "4\n"],
            ['type' => 'result', 'text' => '42'],
            ['type' => 'end_of_execution'],
        ])),
    ]);
    $tool = new RunCodeTool($this->session, app(Sandboxes::class));

    $first = json_decode((string) $tool->handle(new Request(['code' => 'print(2+2); 42'])), true);
    $tool->handle(new Request(['code' => 'print(2+2)']));

    expect($first)->stdout->toBe("4\n")->results->toBe(['42'])
        ->and(AssistantSandbox::query()->sole())->provider_sandbox_id->toBe('sbx1')->access_token->toBe('envd-token')
        ->and(CreditTransaction::query()->where('source_type', CreditTransactionType::AssistantSandbox)->count())->toBe(2);

    Http::assertSentCount(4);
    Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/execute') && $request->hasHeader('X-Access-Token', 'envd-token') && $request['language'] === 'python');
});

it('starts a fresh computer when the old one was deleted while idle, and says so', function () {
    ($this->givePlan)();
    AssistantSandbox::query()->create(['assistant_id' => $this->assistant->id, 'assistant_session_id' => $this->session->id, 'provider' => 'e2b', 'provider_sandbox_id' => 'old']);
    Http::fake([
        'https://api.e2b.test/sandboxes/old/timeout' => Http::response(['message' => 'not found'], 404),
        'https://api.e2b.test/sandboxes' => Http::response(['sandboxID' => 'new']),
        'https://49999-new.e2b.test/execute' => Http::response(($this->ndjson)([['type' => 'error', 'name' => 'NameError', 'value' => "name 'df' is not defined"]])),
    ]);

    $result = json_decode((string) (new RunCodeTool($this->session, app(Sandboxes::class)))->handle(new Request(['code' => 'df.head()'])), true);

    expect($result['note'])->toContain('fresh computer')
        ->and($result['error'])->toContain('NameError')
        ->and(AssistantSandbox::query()->sole()->provider_sandbox_id)->toBe('new');
});

it('saves files as private, versioned artifacts and shows them on the reply', function () {
    $tool = new ExportFileTool($this->session, app(StoreArtifactAction::class), app(DocumentRenderer::class), null);

    $tool->handle(new Request(['filename' => 'notes.md', 'content' => '# v1']));
    $second = json_decode((string) $tool->handle(new Request(['filename' => 'notes.md', 'content' => '# v2'])), true);
    $pdf = json_decode((string) $tool->handle(new Request(['filename' => 'report', 'format' => 'pdf', 'content' => '# Report'])), true);

    expect($second['version'])->toBe(2)
        ->and($pdf)->filename->toBe('report.pdf')->mime_type->toBe('application/pdf');

    $artifact = Artifact::query()->find($second['artifact_id']);
    expect($artifact)->assistant_session_id->toBe($this->session->id)->created_by->toBe($this->owner->id)->general_access->value->toBe('restricted')
        ->and(Storage::disk(config('artifacts.disk'))->get($artifact->path))->toBe('# v2');

    $message = $this->session->messages()->create([
        'role' => 'assistant', 'content' => 'Here you go.',
        'tool_results' => [['id' => 'c1', 'name' => ExportFileTool::NAME, 'arguments' => [], 'result' => json_encode($pdf)]],
    ]);

    expect((new AssistantMessageResource($message))->resolve()['files']->all())
        ->toBe([$pdf]);
});

it('hands over a file made on the computer', function () {
    ($this->givePlan)();
    AssistantSandbox::query()->create(['assistant_id' => $this->assistant->id, 'assistant_session_id' => $this->session->id, 'provider' => 'e2b', 'provider_sandbox_id' => 'sbx1', 'access_token' => 't']);
    Http::fake(['https://49983-sbx1.e2b.test/files*' => Http::response('a,b\n1,2')]);

    $result = json_decode((string) (new ExportFileTool($this->session, app(StoreArtifactAction::class), app(DocumentRenderer::class), app(Sandboxes::class)))
        ->handle(new Request(['filename' => 'data.csv', 'sandbox_path' => '/home/user/data.csv'])), true);

    expect($result)->filename->toBe('data.csv')->mime_type->toBe('text/csv');
    Http::assertSent(fn (HttpRequest $request) => $request['path'] === '/home/user/data.csv');
});

it('shows code the assistant ran on its reply', function () {
    $message = $this->session->messages()->create([
        'role' => 'assistant', 'content' => 'Done.',
        'tool_results' => [['id' => 'c1', 'name' => RunCodeTool::NAME, 'arguments' => ['code' => 'print(1)'], 'result' => json_encode(['stdout' => "1\n", 'seconds' => 1])]],
    ]);

    expect((new AssistantMessageResource($message))->resolve()['code_runs']->all())->toBe([
        ['id' => 'c1', 'language' => 'python', 'code' => 'print(1)', 'output' => ['stdout' => "1\n", 'seconds' => 1]],
    ]);
});
