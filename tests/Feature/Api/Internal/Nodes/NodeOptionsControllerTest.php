<?php

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function workspaceForNodeOptions(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

function connectNodeOptionsAccount(Workspace $workspace, string $connectorKey, string $token = 'token-123'): ConnectorCredential
{
    $connector = Connector::factory()->create(['key' => $connectorKey, 'name' => Str::headline($connectorKey)]);

    return ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)
        ->create(['data' => ['access_token' => $token]]);
}

function nodeOptionsUrl(Workspace $workspace): string
{
    return "/api/v1/workspaces/{$workspace->id}/nodes/options";
}

it('lists the member\'s spreadsheets from google drive with the connected account', function () {
    [$workspace, $owner] = workspaceForNodeOptions();
    connectNodeOptionsAccount($workspace, 'google_sheets', 'sheets-token');
    Http::fake(['www.googleapis.com/drive/v3/files*' => Http::response([
        'files' => [['id' => 'sheet-1', 'name' => 'Budget 2026', 'modifiedTime' => '2026-09-01T10:00:00Z']],
        'nextPageToken' => 'page-2',
    ])]);

    Passport::actingAs($owner);

    $this->postJson(nodeOptionsUrl($workspace), [
        'type' => 'google_sheets_get_values',
        'field' => 'spreadsheet_id',
        'search' => "Budget's",
    ])
        ->assertOk()
        ->assertJsonPath('data.options.0.value', 'sheet-1')
        ->assertJsonPath('data.options.0.label', 'Budget 2026')
        ->assertJsonPath('data.next_cursor', 'page-2');

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $request->hasHeader('Authorization', 'Bearer sheets-token')
            && str_contains($query['q'], "mimeType = 'application/vnd.google-apps.spreadsheet'")
            && str_contains($query['q'], "name contains 'Budget\\'s'");
    });
});

it('lists a spreadsheet\'s tabs as ready-to-use ranges', function () {
    [$workspace, $owner] = workspaceForNodeOptions();
    connectNodeOptionsAccount($workspace, 'google_sheets');
    Http::fake(['sheets.googleapis.com/v4/spreadsheets/sheet-1*' => Http::response(['sheets' => [
        ['properties' => ['sheetId' => 2, 'title' => 'Q1 Sales', 'index' => 1, 'gridProperties' => ['rowCount' => 100, 'columnCount' => 5]]],
        ['properties' => ['sheetId' => 1, 'title' => 'Sheet1', 'index' => 0, 'gridProperties' => ['rowCount' => 1000, 'columnCount' => 26]]],
    ]])]);

    Passport::actingAs($owner);

    $response = $this->postJson(nodeOptionsUrl($workspace), [
        'type' => 'google_sheets_append_values',
        'field' => 'range',
        'config' => ['spreadsheet_id' => 'sheet-1'],
    ])->assertOk();

    expect($response->json('data.options'))->toBe([
        ['value' => 'Sheet1', 'label' => 'Sheet1', 'description' => '1000 rows × 26 columns'],
        ['value' => "'Q1 Sales'", 'label' => 'Q1 Sales', 'description' => '100 rows × 5 columns'],
    ]);
});

it('asks for the spreadsheet before listing its tabs', function () {
    [$workspace, $owner] = workspaceForNodeOptions();
    connectNodeOptionsAccount($workspace, 'google_sheets');
    Http::fake();

    Passport::actingAs($owner);

    $this->postJson(nodeOptionsUrl($workspace), ['type' => 'google_sheets_get_values', 'field' => 'range'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['config.spreadsheet_id']);

    $this->postJson(nodeOptionsUrl($workspace), [
        'type' => 'google_sheets_get_values',
        'field' => 'range',
        'config' => ['spreadsheet_id' => '{{ nodes.find.output.id }}'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['config.spreadsheet_id']);

    Http::assertNothingSent();
});

it('lists slack channels and filters them by the search term', function () {
    [$workspace, $owner] = workspaceForNodeOptions();
    connectNodeOptionsAccount($workspace, 'slack');
    Http::fake(['slack.com/api/conversations.list*' => Http::response([
        'ok' => true,
        'channels' => [
            ['id' => 'C1', 'name' => 'general'],
            ['id' => 'C2', 'name' => 'eng-alerts', 'is_private' => true],
        ],
        'response_metadata' => ['next_cursor' => ''],
    ])]);

    Passport::actingAs($owner);

    $this->postJson(nodeOptionsUrl($workspace), ['type' => 'slack_post_message', 'field' => 'channel', 'search' => 'ENG'])
        ->assertOk()
        ->assertJsonPath('data.options', [['value' => 'C2', 'label' => '#eng-alerts', 'description' => 'Private channel']])
        ->assertJsonPath('data.next_cursor', null);
});

it('turns a slack error into a readable message', function () {
    [$workspace, $owner] = workspaceForNodeOptions();
    connectNodeOptionsAccount($workspace, 'slack');
    Http::fake(['slack.com/*' => Http::response(['ok' => false, 'error' => 'invalid_auth'])]);

    Passport::actingAs($owner);

    $this->postJson(nodeOptionsUrl($workspace), ['type' => 'slack_post_message', 'field' => 'channel'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Slack rejected the connected account. Reconnect it in Apps.');
});

it('does not echo a provider\'s error body', function () {
    [$workspace, $owner] = workspaceForNodeOptions();
    connectNodeOptionsAccount($workspace, 'google_drive');
    Http::fake(['www.googleapis.com/*' => Http::response(['error' => ['message' => 'internal secret detail']], 500)]);

    Passport::actingAs($owner);

    $response = $this->postJson(nodeOptionsUrl($workspace), ['type' => 'google_drive_get_file', 'field' => 'file_id'])
        ->assertUnprocessable();

    expect($response->json('message'))->toBe("Couldn't load options from Google Drive (HTTP 500).");
});

it('lists a repository\'s issues with numeric values and pages by github page number', function () {
    [$workspace, $owner] = workspaceForNodeOptions();
    connectNodeOptionsAccount($workspace, 'github');
    Http::fake(['api.github.com/repos/acme/widgets/issues*' => Http::response([
        ['number' => 7, 'title' => 'Fix login', 'state' => 'open'],
        ['number' => 8, 'title' => 'Add SSO', 'state' => 'closed', 'pull_request' => []],
    ])]);

    Passport::actingAs($owner);

    $this->postJson(nodeOptionsUrl($workspace), [
        'type' => 'github_create_comment',
        'field' => 'issue_number',
        'config' => ['repo' => 'acme/widgets'],
        'cursor' => '2',
    ])
        ->assertOk()
        ->assertJsonPath('data.options.0', ['value' => 7, 'label' => '#7 Fix login', 'description' => 'Issue · open'])
        ->assertJsonPath('data.options.1.description', 'Pull request · closed')
        ->assertJsonPath('data.next_cursor', null);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'page=2'));
});

it('loads gmail messages with their subject and sender', function () {
    [$workspace, $owner] = workspaceForNodeOptions();
    connectNodeOptionsAccount($workspace, 'gmail');
    Http::fake([
        'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::response(['messages' => [['id' => 'm1', 'threadId' => 't1']]]),
        'gmail.googleapis.com/gmail/v1/users/me/messages/m1*' => Http::response(['payload' => ['headers' => [
            ['name' => 'Subject', 'value' => 'Invoice #42'],
            ['name' => 'From', 'value' => 'Billing <billing@example.com>'],
        ]]]),
    ]);

    Passport::actingAs($owner);

    $this->postJson(nodeOptionsUrl($workspace), ['type' => 'gmail_get_message', 'field' => 'message_id'])
        ->assertOk()
        ->assertJsonPath('data.options', [['value' => 'm1', 'label' => 'Invoice #42', 'description' => 'Billing <billing@example.com>']]);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'metadataHeaders=Subject&metadataHeaders=From'));
});

it('pages outlook folders by offset, never by a provider url', function () {
    [$workspace, $owner] = workspaceForNodeOptions();
    connectNodeOptionsAccount($workspace, 'outlook');
    Http::fake(['graph.microsoft.com/v1.0/me/mailFolders*' => Http::response([
        'value' => [['id' => 'f1', 'displayName' => 'Inbox', 'totalItemCount' => 3]],
        '@odata.nextLink' => 'https://evil.example/steal',
    ])]);

    Passport::actingAs($owner);

    $this->postJson(nodeOptionsUrl($workspace), ['type' => 'outlook_move_message', 'field' => 'folder'])
        ->assertOk()
        ->assertJsonPath('data.options.0', ['value' => 'f1', 'label' => 'Inbox', 'description' => '3 messages'])
        ->assertJsonPath('data.next_cursor', null);
});

it('uses a pinned credential, and refuses one from another workspace', function () {
    [$workspace, $owner] = workspaceForNodeOptions();
    connectNodeOptionsAccount($workspace, 'github', 'default-token');
    $pinned = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::where('key', 'github')->first())
        ->create(['data' => ['access_token' => 'pinned-token']]);
    [$otherWorkspace] = workspaceForNodeOptions();
    $foreign = ConnectorCredential::factory()->forWorkspace($otherWorkspace)
        ->forConnector(Connector::where('key', 'github')->first())
        ->create(['data' => ['access_token' => 'foreign-token']]);
    Http::fake(['api.github.com/*' => Http::response([])]);

    Passport::actingAs($owner);

    $this->postJson(nodeOptionsUrl($workspace), [
        'type' => 'github_get_repo',
        'field' => 'repo',
        'config' => ['credential_id' => $pinned->id, 'access_token' => 'client-supplied'],
    ])->assertOk();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer pinned-token'));

    $this->postJson(nodeOptionsUrl($workspace), [
        'type' => 'github_get_repo',
        'field' => 'repo',
        'config' => ['credential_id' => $foreign->id],
    ])->assertUnprocessable();

    Http::assertNotSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer foreign-token'));
});

it('does not use another member\'s personal account', function () {
    [$workspace, $owner] = workspaceForNodeOptions();
    $teammate = User::factory()->create();
    $workspace->members()->create(['user_id' => $teammate->id, 'role' => Role::Admin, 'joined_at' => now()]);
    $credential = connectNodeOptionsAccount($workspace, 'slack');
    $credential->forceFill(['scope' => ConnectorCredentialScope::Personal->value, 'created_by' => $owner->id])->save();
    Http::fake();

    Passport::actingAs($teammate);

    $this->postJson(nodeOptionsUrl($workspace), ['type' => 'slack_post_message', 'field' => 'channel'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Connect a Slack account (or choose one in Account) to load this list.');

    Http::assertNothingSent();
});

it('asks the member to connect an account when none exists', function () {
    [$workspace, $owner] = workspaceForNodeOptions();
    Http::fake();

    Passport::actingAs($owner);

    $this->postJson(nodeOptionsUrl($workspace), ['type' => 'google_calendar_create_event', 'field' => 'calendar_id'])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn (string $message): bool => str_starts_with($message, 'Connect a '));
});

it('lists this workspace\'s agents and workflows without any connected account', function () {
    [$workspace, $owner] = workspaceForNodeOptions();
    [$otherWorkspace] = workspaceForNodeOptions();
    $agent = Agent::factory()->forWorkspace($workspace)->create(['name' => 'Support Bot']);
    Agent::factory()->forWorkspace($otherWorkspace)->create(['name' => 'Foreign Bot']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create(['name' => 'Nightly sync']);
    Workflow::factory()->forWorkspace($workspace)->create(['name' => 'Hidden', 'is_internal' => true]);

    Passport::actingAs($owner);

    $agents = $this->postJson(nodeOptionsUrl($workspace), ['type' => 'agent', 'field' => 'agent_id'])->assertOk();
    expect(collect($agents->json('data.options'))->pluck('value')->all())->toBe([$agent->id]);

    $workflows = $this->postJson(nodeOptionsUrl($workspace), ['type' => 'subflow', 'field' => 'workflow_id'])->assertOk();
    expect(collect($workflows->json('data.options'))->pluck('label')->all())->toBe(['Nightly sync'])
        ->and($workflows->json('data.options.0.value'))->toBe($workflow->id);
});

it('lists timezones for the schedule trigger without any connected account', function () {
    [$workspace, $owner] = workspaceForNodeOptions();

    Passport::actingAs($owner);

    $timezones = $this->postJson(nodeOptionsUrl($workspace), [
        'type' => 'schedule_trigger',
        'field' => 'timezone',
        'search' => 'Kolkata',
    ])->assertOk();

    expect(collect($timezones->json('data.options'))->pluck('value')->all())->toContain('Asia/Kolkata');
});

it('rejects a field that has no options and an unknown node type', function () {
    [$workspace, $owner] = workspaceForNodeOptions();

    Passport::actingAs($owner);

    $this->postJson(nodeOptionsUrl($workspace), ['type' => 'slack_post_message', 'field' => 'text'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['field']);

    $this->postJson(nodeOptionsUrl($workspace), ['type' => 'nope', 'field' => 'channel'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('forbids non-members', function () {
    [$workspace] = workspaceForNodeOptions();

    Passport::actingAs(User::factory()->create());

    $this->postJson(nodeOptionsUrl($workspace), ['type' => 'agent', 'field' => 'agent_id'])->assertForbidden();
});
