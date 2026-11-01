<?php

use App\Exceptions\ConnectorException;
use App\Models\Agents\Agent;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\GitHubOptions;
use App\Services\Workflows\NodeOptions\Sources\GmailOptions;
use App\Services\Workflows\NodeOptions\Sources\GoogleAnalyticsOptions;
use App\Services\Workflows\NodeOptions\Sources\GoogleCalendarOptions;
use App\Services\Workflows\NodeOptions\Sources\GoogleDocsOptions;
use App\Services\Workflows\NodeOptions\Sources\GoogleDriveOptions;
use App\Services\Workflows\NodeOptions\Sources\GoogleFormsOptions;
use App\Services\Workflows\NodeOptions\Sources\GoogleSearchConsoleOptions;
use App\Services\Workflows\NodeOptions\Sources\GoogleSheetsOptions;
use App\Services\Workflows\NodeOptions\Sources\GoogleSlidesOptions;
use App\Services\Workflows\NodeOptions\Sources\GoogleTasksOptions;
use App\Services\Workflows\NodeOptions\Sources\OutlookOptions;
use App\Services\Workflows\NodeOptions\Sources\SlackOptions;
use App\Services\Workflows\NodeOptions\Sources\WorkspaceOptions;
use App\Services\Workflows\NodeOptions\Sources\YouTubeOptions;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, mixed>  $config
 */
function optionsQuery(array $config = [], ?string $search = null, ?string $cursor = null): NodeOptionsQuery
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return new NodeOptionsQuery($workspace, $owner, $config, 'test-token', $search, $cursor);
}

/**
 * Each source turns a realistic provider response into options: the first
 * option and the next-page cursor are checked against what the provider sent.
 */
dataset('provider sources', [
    'slack users' => [SlackOptions::class, 'slack.users', [], [
        'slack.com/api/users.list*' => ['ok' => true, 'members' => [
            ['id' => 'USLACKBOT', 'name' => 'slackbot'],
            ['id' => 'U9', 'name' => 'gone', 'deleted' => true],
            ['id' => 'U1', 'name' => 'ada', 'real_name' => 'Ada Lovelace'],
        ], 'response_metadata' => ['next_cursor' => 'dXNlcjpVMDYx']],
    ], ['value' => 'U1', 'label' => 'Ada Lovelace', 'description' => '@ada'], 'dXNlcjpVMDYx'],

    'gmail labels' => [GmailOptions::class, 'gmail.labels', [], [
        'gmail.googleapis.com/*' => ['labels' => [
            ['id' => 'INBOX', 'name' => 'INBOX', 'type' => 'system'],
            ['id' => 'Label_1', 'name' => 'Receipts', 'type' => 'user'],
        ]],
    ], ['value' => 'Label_1', 'label' => 'Receipts'], null],

    'gmail threads' => [GmailOptions::class, 'gmail.threads', [], [
        'gmail.googleapis.com/*' => ['threads' => [['id' => 't1', 'snippet' => 'Tom &amp; Jerry']], 'nextPageToken' => 'p2'],
    ], ['value' => 't1', 'label' => 'Tom & Jerry'], 'p2'],

    'github repos' => [GitHubOptions::class, 'github.repos', [], [
        'api.github.com/user/repos*' => [['full_name' => 'acme/widgets', 'private' => true]],
    ], ['value' => 'acme/widgets', 'label' => 'acme/widgets', 'description' => 'Private'], null],

    'github owners' => [GitHubOptions::class, 'github.owners', [], [
        'api.github.com/user/orgs*' => [['login' => 'acme', 'description' => 'Acme Inc']],
    ], ['value' => 'acme', 'label' => 'acme', 'description' => 'Acme Inc'], null],

    'github branches' => [GitHubOptions::class, 'github.branches', ['repo' => 'acme/widgets'], [
        'api.github.com/repos/acme/widgets/branches*' => [['name' => 'main'], ['commit' => []]],
    ], ['value' => 'main', 'label' => 'main'], null],

    'github labels' => [GitHubOptions::class, 'github.labels', ['repo' => 'acme/widgets'], [
        'api.github.com/repos/acme/widgets/labels*' => [['name' => 'bug', 'description' => 'Something is broken']],
    ], ['value' => 'bug', 'label' => 'bug', 'description' => 'Something is broken'], null],

    'github assignees' => [GitHubOptions::class, 'github.assignees', ['repo' => 'acme/widgets'], [
        'api.github.com/repos/acme/widgets/assignees*' => [['login' => 'octocat']],
    ], ['value' => 'octocat', 'label' => 'octocat'], null],

    'drive files' => [GoogleDriveOptions::class, 'google_drive.files', [], [
        'www.googleapis.com/drive/v3/files*' => ['files' => [['id' => 'f1', 'name' => 'Notes.pdf', 'mimeType' => 'application/pdf']]],
    ], ['value' => 'f1', 'label' => 'Notes.pdf', 'description' => 'Pdf'], null],

    'drive folders' => [GoogleDriveOptions::class, 'google_drive.folders', [], [
        'www.googleapis.com/drive/v3/files*' => ['files' => [['id' => 'dir1', 'name' => 'Invoices', 'mimeType' => 'application/vnd.google-apps.folder']]],
    ], ['value' => 'dir1', 'label' => 'Invoices'], null],

    'tasks lists' => [GoogleTasksOptions::class, 'google_tasks.lists', [], [
        'tasks.googleapis.com/tasks/v1/users/@me/lists*' => ['items' => [['id' => 'l1', 'title' => 'Work']], 'nextPageToken' => 'n2'],
    ], ['value' => 'l1', 'label' => 'Work'], 'n2'],

    'tasks open tasks' => [GoogleTasksOptions::class, 'google_tasks.tasks', ['task_list_id' => 'l1'], [
        'tasks.googleapis.com/tasks/v1/lists/l1/tasks*' => ['items' => [['id' => 't1', 'title' => 'Pay rent', 'due' => '2026-03-05T00:00:00.000Z']]],
    ], ['value' => 't1', 'label' => 'Pay rent', 'description' => 'Due Mar 5, 2026'], null],

    'forms' => [GoogleFormsOptions::class, 'google_forms.forms', [], [
        'www.googleapis.com/drive/v3/files*' => ['files' => [['id' => 'f1', 'name' => 'Feedback']]],
    ], ['value' => 'f1', 'label' => 'Feedback'], null],

    'slides presentations' => [GoogleSlidesOptions::class, 'google_slides.presentations', [], [
        'www.googleapis.com/drive/v3/files*' => ['files' => [['id' => 'p1', 'name' => 'Review']]],
    ], ['value' => 'p1', 'label' => 'Review'], null],

    'analytics properties' => [GoogleAnalyticsOptions::class, 'google_analytics.properties', [], [
        'analyticsadmin.googleapis.com/v1beta/accountSummaries*' => ['accountSummaries' => [
            ['displayName' => 'Acme', 'propertySummaries' => [['property' => 'properties/111', 'displayName' => 'Web']]],
        ]],
    ], ['value' => '111', 'label' => 'Web', 'description' => 'Acme'], null],

    'search console sites' => [GoogleSearchConsoleOptions::class, 'google_search_console.sites', [], [
        'www.googleapis.com/webmasters/v3/sites*' => ['siteEntry' => [['siteUrl' => 'sc-domain:example.com', 'permissionLevel' => 'siteOwner']]],
    ], ['value' => 'sc-domain:example.com', 'label' => 'sc-domain:example.com', 'description' => 'Owner'], null],

    'youtube playlists' => [YouTubeOptions::class, 'google_youtube.playlists', [], [
        'www.googleapis.com/youtube/v3/playlists*' => ['items' => [['id' => 'PL1', 'snippet' => ['title' => 'Favourites'], 'contentDetails' => ['itemCount' => 12]]]],
    ], ['value' => 'PL1', 'label' => 'Favourites', 'description' => '12 videos'], null],

    'docs documents' => [GoogleDocsOptions::class, 'google_docs.documents', [], [
        'www.googleapis.com/drive/v3/files*' => ['files' => [['id' => 'd1', 'name' => 'Spec']], 'nextPageToken' => 'n2'],
    ], ['value' => 'd1', 'label' => 'Spec'], 'n2'],

    'calendar calendars' => [GoogleCalendarOptions::class, 'google_calendar.calendars', [], [
        'www.googleapis.com/calendar/v3/users/me/calendarList*' => ['items' => [
            ['id' => 'team@group.calendar.google.com', 'summary' => 'Team'],
            ['id' => 'me@example.com', 'summary' => 'Me', 'primary' => true],
        ]],
    ], ['value' => 'primary', 'label' => 'Me', 'description' => 'Primary calendar'], null],

    'calendar events' => [GoogleCalendarOptions::class, 'google_calendar.events', ['calendar_id' => 'team@group.calendar.google.com'], [
        'www.googleapis.com/calendar/v3/calendars/team%40group.calendar.google.com/events*' => ['items' => [
            ['id' => 'e1', 'summary' => 'Standup', 'start' => ['dateTime' => '2026-10-06T09:00:00Z']],
        ]],
    ], ['value' => 'e1', 'label' => 'Standup', 'description' => '2026-10-06T09:00:00Z'], null],

    'outlook messages' => [OutlookOptions::class, 'outlook.messages', [], [
        'graph.microsoft.com/v1.0/me/mailFolders/inbox/messages*' => ['value' => [
            ['id' => 'm1', 'subject' => '', 'from' => ['emailAddress' => ['address' => 'a@example.com']]],
        ]],
    ], ['value' => 'm1', 'label' => '(no subject)', 'description' => 'a@example.com'], null],

    'outlook calendars' => [OutlookOptions::class, 'outlook.calendars', [], [
        'graph.microsoft.com/v1.0/me/calendars*' => ['value' => [['id' => 'AAMk_1=', 'name' => 'Calendar', 'isDefaultCalendar' => true]]],
    ], ['value' => 'AAMk_1=', 'label' => 'Calendar', 'description' => 'Main calendar'], null],

    'outlook events' => [OutlookOptions::class, 'outlook.events', [], [
        'graph.microsoft.com/v1.0/me/calendarView*' => ['value' => [['id' => 'ev1', 'subject' => 'Review', 'start' => ['dateTime' => '2026-10-07T10:00:00']]]],
    ], ['value' => 'ev1', 'label' => 'Review', 'description' => '2026-10-07T10:00:00'], null],
]);

it('maps provider responses into options', function (string $class, string $source, array $config, array $fakes, array $firstOption, ?string $nextCursor) {
    Http::fake(array_map(fn (array $body) => Http::response($body), $fakes));

    $page = app($class)->load($source, optionsQuery($config))->toArray();

    expect($page['options'][0])->toBe($firstOption)
        ->and($page['next_cursor'])->toBe($nextCursor);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-token'));
})->with('provider sources');

it('quotes sheet tab names only when sheets needs them', function (string $title, string $range) {
    expect(GoogleSheetsOptions::a1SheetName($title))->toBe($range);
})->with([
    ['Sheet1', 'Sheet1'],
    ['Q1 Sales', "'Q1 Sales'"],
    ["Bob's", "'Bob''s'"],
    ['AB12', "'AB12'"],
    ['R1C1', "'R1C1'"],
    ['2026', "'2026'"],
]);

it('rejects a repository that is not owner/name before calling github', function (string $repo) {
    Http::fake();

    expect(fn () => app(GitHubOptions::class)->load('github.branches', optionsQuery(['repo' => $repo])))
        ->toThrow(ValidationException::class);

    Http::assertNothingSent();
})->with(['widgets', 'acme/..', 'acme/widgets/../../user', 'acme/widgets?x=1', '']);

it('ignores a non-numeric page cursor instead of forwarding it', function () {
    Http::fake(['api.github.com/*' => Http::response([])]);

    app(GitHubOptions::class)->load('github.repos', optionsQuery(cursor: '2; drop table'));

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'page=1'));
});

it('escapes quotes in a drive name search', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(['files' => []])]);

    app(GoogleDriveOptions::class)->load('google_drive.files', optionsQuery(search: "Bob's \\ notes"));

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_contains($query['q'], "name contains 'Bob\\'s \\\\ notes'");
    });
});

it('lists only folders for the folder picker', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(['files' => []])]);

    app(GoogleDriveOptions::class)->load('google_drive.folders', optionsQuery());

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_contains($query['q'], "mimeType = 'application/vnd.google-apps.folder'");
    });
});

it('names a gmail message by its id when its metadata cannot be read', function () {
    Http::fake([
        'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::response(['messages' => [['id' => 'm1']]]),
        'gmail.googleapis.com/gmail/v1/users/me/messages/m1*' => Http::response([], 500),
    ]);

    $page = app(GmailOptions::class)->load('gmail.messages', optionsQuery())->toArray();

    expect($page['options'])->toBe([['value' => 'm1', 'label' => '(no subject)']]);
});

it('reports a provider that cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    expect(fn () => app(OutlookOptions::class)->load('outlook.folders', optionsQuery()))
        ->toThrow(ConnectorException::class, "Couldn't reach Outlook. Try again in a moment.");
});

it('searches workspace records case-insensitively and literally', function () {
    $query = optionsQuery(search: '50%');
    Agent::factory()->forWorkspace($query->workspace)->create(['name' => 'Discount 50% bot']);
    Agent::factory()->forWorkspace($query->workspace)->create(['name' => 'Discount 500 bot']);

    $labels = collect(app(WorkspaceOptions::class)->load('workspace.agents', $query)->options)->pluck('label');

    expect($labels->all())->toBe(['Discount 50% bot']);
})->skip(fn () => config('database.default') === 'sqlite', 'SQLite has no default LIKE escape character.');

it('pages workspace records by offset', function () {
    $query = optionsQuery();
    Workflow::factory()->count(51)->forWorkspace($query->workspace)->create();

    $first = app(WorkspaceOptions::class)->load('workspace.workflows', $query);
    $second = app(WorkspaceOptions::class)->load('workspace.workflows', new NodeOptionsQuery($query->workspace, $query->user, [], cursor: $first->nextCursor));

    expect($first->options)->toHaveCount(50)
        ->and($first->nextCursor)->toBe('50')
        ->and($second->options)->toHaveCount(1)
        ->and($second->nextCursor)->toBeNull();
});
