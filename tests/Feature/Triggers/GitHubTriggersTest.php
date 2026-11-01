<?php

use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\GitHub\GitHubListBranchesNode;
use App\Nodes\Integrations\GitHub\GitHubListClosedIssuesNode;
use App\Nodes\Integrations\GitHub\GitHubListCommitsNode;
use App\Nodes\Integrations\GitHub\GitHubListForksNode;
use App\Nodes\Integrations\GitHub\GitHubListIssuesNode;
use App\Nodes\Integrations\GitHub\GitHubListMergedPullRequestsNode;
use App\Nodes\Integrations\GitHub\GitHubListReleasesNode;
use App\Nodes\Integrations\GitHub\GitHubListRepoCommentsNode;
use App\Nodes\Integrations\GitHub\GitHubListReposNode;
use App\Nodes\Integrations\GitHub\GitHubListStargazersNode;
use App\Nodes\Integrations\GitHub\GitHubListWorkflowRunsNode;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const GH_TRIGGER_BASE = ['access_token' => 'ghp-test', 'repo' => 'acme/widgets'];

/**
 * A published workflow whose first step is a GitHub reader node switched on as
 * a trigger on acme/widgets, with GitHub push on and the subscription synced.
 */
function githubTrigger(string $nodeType, array $nodeConfig = []): Trigger
{
    config(['triggers.push.github.enabled' => true]);
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    Http::fake(['api.github.com/repos/acme/widgets/hooks' => Http::response(['id' => 55])]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'github') ?? Connector::factory()->create(['key' => 'github']))
        ->create(['data' => ['access_token' => 'ghp-test'], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true, 'repo' => 'Acme/Widgets', ...$nodeConfig]],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
    SyncTriggerSubscription::dispatchSync($trigger->id);

    return $trigger->fresh();
}

describe('instant delivery: a repository webhook wakes the trigger\'s own poll', function () {
    it('listens for the right GitHub event for each trigger', function (string $nodeType, string $event) {
        $trigger = githubTrigger($nodeType);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe("acme/widgets:{$event}")
            ->and($trigger->subscription->routing_key)->toBe('acme/widgets#55');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/repos/acme/widgets/hooks') && $request['events'] === [$event] && $request['config']['url'] === url('/api/hooks/apps/github'));
    })->with([
        'new issue' => ['github_list_issues', 'issues'],
        'issue closed' => ['github_list_closed_issues', 'issues'],
        'new pull request' => ['github_list_pull_requests', 'pull_request'],
        'pull request merged' => ['github_list_merged_pull_requests', 'pull_request'],
        'new commit' => ['github_list_commits', 'push'],
        'new release' => ['github_list_releases', 'release'],
        'new star' => ['github_list_stargazers', 'watch'],
        'new fork' => ['github_list_forks', 'fork'],
        'new branch' => ['github_list_branches', 'create'],
        'new comment' => ['github_list_repo_comments', 'issue_comment'],
        'workflow run' => ['github_list_workflow_runs', 'workflow_run'],
    ]);

    it('pokes the poll when a signed webhook arrives, and shares one hook between triggers of the same event', function () {
        $trigger = githubTrigger('github_list_stargazers');
        $watch = ProviderWatch::query()->sole();
        $body = json_encode(['action' => 'created', 'repository' => ['full_name' => 'Acme/Widgets']]);

        $this->call('POST', '/api/hooks/apps/github', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'watch',
            'HTTP_X_GITHUB_DELIVERY' => 'd-1',
            'HTTP_X_GITHUB_HOOK_ID' => '55',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $watch->secret),
        ], $body)->assertOk();

        Queue::assertPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->trigger->is($trigger));
    });

    it('leaves a trigger with no repository to polling', function () {
        $trigger = githubTrigger('github_list_repos', ['repo' => '']);

        expect($trigger->subscription)->toBeNull();
    });
});

describe('what each trigger reads', function () {
    it('treats pull requests in the issues list as pull requests, not issues, and can filter by label', function () {
        Http::fake(['api.github.com/*' => Http::response([
            ['number' => 1, 'title' => 'A bug'],
            ['number' => 2, 'title' => 'A PR', 'pull_request' => ['url' => 'x']],
        ])]);
        $node = new GitHubListIssuesNode;

        $output = $node->execute(Run::factory()->create(), [...GH_TRIGGER_BASE, 'labels' => ['bug', 'help wanted']], []);

        expect(array_column($node->triggerItems($output, []), 'number'))->toBe([1])
            ->and(count($output['issues']))->toBe(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'labels=bug%2Chelp%20wanted') || str_contains($request->url(), 'labels=bug%2Chelp+wanted'));
    });

    it('reads commits as a trigger, by sha and newest first', function () {
        $node = new GitHubListCommitsNode;

        expect($node->triggerItems(['commits' => [['sha' => 'a1'], 'junk']], []))->toHaveCount(1)
            ->and($node->triggerItemId(['sha' => 'a1']))->toBe('a1')
            ->and($node->triggerListsNewestFirst())->toBeTrue()
            ->and($node->triggerName())->toBe('GitHub: New commit')
            ->and($node->triggerReadConfig([])['per_page'])->toBe(100);
    });

    it('reads a user\'s newest repositories as a trigger', function () {
        Http::fake(['api.github.com/*' => Http::response([['full_name' => 'me/new']])]);
        $node = new GitHubListReposNode;

        $node->execute(Run::factory()->create(), $node->triggerReadConfig(['access_token' => 'ghp-test']), []);
        $node->execute(Run::factory()->create(), ['access_token' => 'ghp-test'], []);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sort=created') && str_contains($request->url(), 'direction=desc'));
        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'sort='));
        expect($node->triggerName())->toBe('GitHub: New repository');
    });

    it('skips draft releases', function () {
        $node = new GitHubListReleasesNode;

        $items = $node->triggerItems(['releases' => [['id' => 1, 'draft' => false], ['id' => 2, 'draft' => true], ['id' => 3]]], []);

        expect(array_column($items, 'id'))->toBe([1, 3])
            ->and($node->triggerItemId(['id' => 1]))->toBe('1')
            ->and($node->triggerName())->toBe('GitHub: New release');
    });

    it('lists forks newest first', function () {
        Http::fake(['api.github.com/*' => Http::response([['full_name' => 'a/widgets']])]);
        $node = new GitHubListForksNode;

        $node->execute(Run::factory()->create(), GH_TRIGGER_BASE, []);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/forks') && str_contains($request->url(), 'sort=newest'));
        expect($node->triggerListsNewestFirst())->toBeTrue()->and($node->triggerName())->toBe('GitHub: New fork');
    });

    it('names a branch by its name, so a branch seen once never runs twice', function () {
        Http::fake(['api.github.com/*' => Http::response([['name' => 'main'], ['name' => 'dev']])]);
        $node = new GitHubListBranchesNode;

        $output = $node->execute(Run::factory()->create(), [...GH_TRIGGER_BASE, 'protected_only' => true], []);

        expect(array_map($node->triggerItemId(...), $node->triggerItems($output, [])))->toBe(['main', 'dev'])
            ->and($node->triggerName())->toBe('GitHub: New branch');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'protected=true'));
    });

    it('reads the newest comments on issues and pull requests', function () {
        Http::fake(['api.github.com/*' => Http::response([['id' => 9, 'body' => 'hi']])]);
        $node = new GitHubListRepoCommentsNode;

        $node->execute(Run::factory()->create(), GH_TRIGGER_BASE, []);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/issues/comments') && str_contains($request->url(), 'sort=created') && str_contains($request->url(), 'direction=desc'));
        expect($node->triggerName())->toBe('GitHub: New comment')->and($node->triggerItemId(['id' => 9]))->toBe('9');
    });

    it('lists finished workflow runs, per attempt, and can narrow to a workflow, branch or failures', function () {
        Http::fake(['api.github.com/*' => Http::response(['workflow_runs' => [['id' => 7, 'run_attempt' => 2, 'conclusion' => 'failure']], 'total_count' => 1])]);
        $node = new GitHubListWorkflowRunsNode;

        $all = $node->execute(Run::factory()->create(), GH_TRIGGER_BASE, []);
        $failed = $node->execute(Run::factory()->create(), [...GH_TRIGGER_BASE, 'status' => 'failure', 'workflow' => 'ci.yml', 'branch' => 'main'], []);

        expect($all['runs'])->toHaveCount(1)
            ->and($failed['total'])->toBe(1)
            ->and($node->triggerItemId($all['runs'][0]))->toBe('7:2')
            ->and($node->triggerItemId(['id' => 8]))->toBe('8:1')
            ->and($node->triggerName())->toBe('GitHub: Workflow run finished');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/actions/runs') && str_contains($request->url(), 'status=completed'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/actions/workflows/ci.yml/runs') && str_contains($request->url(), 'status=failure') && str_contains($request->url(), 'branch=main'));
    });

    it('triggers on merged pull requests only, not ones closed without merging', function () {
        $node = new GitHubListMergedPullRequestsNode;

        $items = $node->triggerItems(['pull_requests' => [
            ['number' => 1, 'merged_at' => '2026-03-01T10:00:00Z'],
            ['number' => 2, 'merged_at' => null],
            ['number' => 3],
        ]], []);

        expect(array_column($items, 'number'))->toBe([1])
            ->and($node->triggerItemId($items[0]))->toBe('1')
            ->and($node->triggerName())->toBe('GitHub: Pull request merged');
    });

    it('triggers on closed issues once per closing, and not on pull requests', function () {
        $node = new GitHubListClosedIssuesNode;

        $items = $node->triggerItems(['issues' => [
            ['number' => 1, 'closed_at' => '2026-03-01T10:00:00Z'],
            ['number' => 2, 'closed_at' => '2026-03-01T10:00:00Z', 'pull_request' => ['url' => 'x']],
            ['number' => 3, 'closed_at' => null],
        ]], []);

        expect(array_column($items, 'number'))->toBe([1])
            ->and($node->triggerItemId($items[0]))->toBe('1:2026-03-01T10:00:00Z')
            ->and($node->triggerName())->toBe('GitHub: Issue closed');
    });
});

describe('stars', function () {
    function starPage(int $from, int $to): array
    {
        return array_map(fn (int $number): array => ['starred_at' => "2026-03-01T10:00:{$number}Z", 'user' => ['login' => "user{$number}", 'id' => $number, 'html_url' => "https://github.com/user{$number}"]], range($from, $to));
    }

    it('reads the newest stars from the last pages, with when each was given', function () {
        Http::fake(['api.github.com/repos/acme/widgets/stargazers*' => function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 1);

            return match ($page) {
                1 => Http::response(starPage(1, 3), 200, ['Link' => '<https://api.github.com/repositories/1/stargazers?per_page=100&page=3>; rel="next", <https://api.github.com/repositories/1/stargazers?per_page=100&page=3>; rel="last"']),
                2 => Http::response(starPage(10, 12)),
                default => Http::response(starPage(20, 21)),
            };
        }]);
        $node = new GitHubListStargazersNode;

        $output = $node->execute(Run::factory()->create(), [...GH_TRIGGER_BASE, 'limit' => 4], []);

        expect(array_column($output['stargazers'], 'login'))->toBe(['user11', 'user12', 'user20', 'user21'])
            ->and($output['stargazers'][3])->toBe(['login' => 'user21', 'id' => 21, 'url' => 'https://github.com/user21', 'avatar_url' => null, 'starred_at' => '2026-03-01T10:00:21Z']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'page=3') && $request->hasHeader('Accept', 'application/vnd.github.star+json'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'page=2'));
    });

    it('reads a repository whose stars fit one page with a single request', function () {
        Http::fake(['api.github.com/*' => Http::response(starPage(1, 3))]);
        $node = new GitHubListStargazersNode;

        $output = $node->execute(Run::factory()->create(), GH_TRIGGER_BASE, []);

        expect(array_column($output['stargazers'], 'login'))->toBe(['user1', 'user2', 'user3']);
        // One call finds there is a single page, one reads it.
        Http::assertSentCount(2);
    });

    it('keeps only as many of the newest stars as one poll looks at', function () {
        config(['triggers.poll_max_items' => 2]);
        $node = new GitHubListStargazersNode;

        $flat = array_map(fn (array $entry): array => ['login' => $entry['user']['login']], starPage(1, 5));
        $items = $node->triggerItems(['stargazers' => $flat], []);

        expect(array_column($items, 'login'))->toBe(['user4', 'user5'])
            ->and($node->triggerItemId($items[0]))->toBe('user4')
            ->and($node->triggerName())->toBe('GitHub: New star');
    });

    it('shows the error when GitHub refuses', function () {
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

        (new GitHubListStargazersNode)->execute(Run::factory()->create(), GH_TRIGGER_BASE, []);
    })->throws(RuntimeException::class, 'Not Found');
});

it('offers every GitHub and Slack trigger in the Triggers & Events catalog', function () {
    $names = fn (string $category): array => collect(app(NodeRegistry::class)->triggerCatalog())
        ->where('category', $category)->pluck('name')->sort()->values()->all();

    expect($names('github'))->toBe([
        'GitHub: Issue closed', 'GitHub: New branch', 'GitHub: New comment', 'GitHub: New commit', 'GitHub: New fork',
        'GitHub: New issue', 'GitHub: New pull request', 'GitHub: New release', 'GitHub: New repository', 'GitHub: New star',
        'GitHub: Pull request merged', 'GitHub: Workflow run finished',
    ])->and($names('slack'))->toBe([
        'Slack: App mentioned', 'Slack: Direct message', 'Slack: Message pinned', 'Slack: New channel', 'Slack: New file',
        'Slack: New message', 'Slack: New person', 'Slack: Person joined channel', 'Slack: Reaction added', 'Slack: Reaction removed',
    ]);
});
