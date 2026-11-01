<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\GitHub\GitHubCreateCommentNode;
use App\Nodes\Integrations\GitHub\GitHubCreateIssueNode;
use App\Nodes\Integrations\GitHub\GitHubCreatePullRequestNode;
use App\Nodes\Integrations\GitHub\GitHubCreateRepoNode;
use App\Nodes\Integrations\GitHub\GitHubGetIssueNode;
use App\Nodes\Integrations\GitHub\GitHubGetRepoNode;
use App\Nodes\Integrations\GitHub\GitHubListCommitsNode;
use App\Nodes\Integrations\GitHub\GitHubListIssuesNode;
use App\Nodes\Integrations\GitHub\GitHubListPullRequestsNode;
use App\Nodes\Integrations\GitHub\GitHubListReposNode;
use App\Nodes\Integrations\GitHub\GitHubMergePullRequestNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

it('registers every github node under its own type string', function () {
    $registry = app(NodeRegistry::class);

    $types = [
        'github_get_repo' => GitHubGetRepoNode::class,
        'github_list_repos' => GitHubListReposNode::class,
        'github_create_repo' => GitHubCreateRepoNode::class,
        'github_list_issues' => GitHubListIssuesNode::class,
        'github_create_issue' => GitHubCreateIssueNode::class,
        'github_create_comment' => GitHubCreateCommentNode::class,
        'github_list_pull_requests' => GitHubListPullRequestsNode::class,
        'github_create_pull_request' => GitHubCreatePullRequestNode::class,
        'github_list_commits' => GitHubListCommitsNode::class,
    ];

    foreach ($types as $type => $class) {
        expect($registry->has($type))->toBeTrue();
        $node = $registry->resolve($type);
        expect($node)->toBeInstanceOf($class);
        expect($node->category())->toBe('github');
    }
});

it('gets a repository with the bearer token and github accept header', function () {
    Http::fake(['api.github.com/repos/acme/widgets' => Http::response(['id' => 1, 'full_name' => 'acme/widgets'])]);

    $node = new GitHubGetRepoNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'gh-test', 'repo' => 'acme/widgets'], []);

    expect($output)->toBe(['id' => 1, 'full_name' => 'acme/widgets']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.github.com/repos/acme/widgets'
            && $request->hasHeader('Authorization', 'Bearer gh-test')
            && $request->hasHeader('Accept', 'application/vnd.github+json');
    });
});

it('throws when github answers a non-2xx status', function () {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

    $node = new GitHubGetRepoNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['access_token' => 'gh-test', 'repo' => 'acme/missing'], []))
        ->toThrow(RuntimeException::class, 'Not Found');
});

it('throws when access_token is missing', function () {
    $node = new GitHubGetRepoNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['repo' => 'acme/widgets'], []))
        ->toThrow(RuntimeException::class, 'access_token');
});

it('lists repos for the authenticated user when no owner is given', function () {
    Http::fake(['api.github.com/user/repos*' => Http::response([['id' => 1]])]);

    $node = new GitHubListReposNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'gh-test'], []);

    expect($output['repos'])->toBe([['id' => 1]]);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/user/repos'));
});

it('lists repos for an organization when owner is given', function () {
    Http::fake(['api.github.com/orgs/acme/repos*' => Http::response([['id' => 2]])]);

    $node = new GitHubListReposNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'gh-test', 'owner' => 'acme'], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/orgs/acme/repos'));
});

it('lists issues with a default open state', function () {
    Http::fake(['api.github.com/repos/acme/widgets/issues*' => Http::response([['number' => 1]])]);

    $node = new GitHubListIssuesNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'gh-test', 'repo' => 'acme/widgets'], []);

    expect($output['issues'])->toBe([['number' => 1]]);
    Http::assertSent(fn ($request) => $request['state'] === 'open');
});

it('creates a repository for the authenticated user', function () {
    Http::fake(['api.github.com/user/repos' => Http::response([
        'id' => 7, 'full_name' => 'me/widgets', 'private' => true, 'html_url' => 'https://github.com/me/widgets',
    ], 201)]);

    $node = new GitHubCreateRepoNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'gh-test', 'name' => 'widgets', 'private' => true], []);

    expect($output)->toBe([
        'id' => 7, 'full_name' => 'me/widgets', 'private' => true, 'url' => 'https://github.com/me/widgets',
    ]);
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['name'] === 'widgets'
        && $request['private'] === true
        && ! array_key_exists('description', $request->data()));
});

it('creates a repository under an organization when an owner is given', function () {
    Http::fake(['api.github.com/orgs/acme/repos' => Http::response(['id' => 8, 'full_name' => 'acme/widgets'], 201)]);

    $node = new GitHubCreateRepoNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'gh-test', 'owner' => 'acme', 'name' => 'widgets'], []);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/orgs/acme/repos');
});

it('creates an issue and maps the response', function () {
    Http::fake(['api.github.com/repos/acme/widgets/issues' => Http::response([
        'id' => 10, 'number' => 5, 'title' => 'Bug', 'html_url' => 'https://github.com/acme/widgets/issues/5',
    ])]);

    $node = new GitHubCreateIssueNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'gh-test', 'repo' => 'acme/widgets', 'title' => 'Bug'], []);

    expect($output)->toBe([
        'id' => 10, 'number' => 5, 'title' => 'Bug', 'url' => 'https://github.com/acme/widgets/issues/5',
    ]);
});

it('creates a comment on an issue', function () {
    Http::fake(['api.github.com/repos/acme/widgets/issues/5/comments' => Http::response([
        'id' => 99, 'html_url' => 'https://github.com/acme/widgets/issues/5#comment-99',
    ])]);

    $node = new GitHubCreateCommentNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'access_token' => 'gh-test', 'repo' => 'acme/widgets', 'issue_number' => 5, 'body' => 'Looking into it.',
    ], []);

    expect($output)->toBe(['id' => 99, 'url' => 'https://github.com/acme/widgets/issues/5#comment-99']);
    Http::assertSent(fn ($request) => $request['body'] === 'Looking into it.');
});

it('lists pull requests with a default open state', function () {
    Http::fake(['api.github.com/repos/acme/widgets/pulls*' => Http::response([['number' => 3]])]);

    $node = new GitHubListPullRequestsNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'gh-test', 'repo' => 'acme/widgets'], []);

    expect($output['pull_requests'])->toBe([['number' => 3]]);
});

it('creates a pull request and maps the response', function () {
    Http::fake(['api.github.com/repos/acme/widgets/pulls' => Http::response([
        'id' => 11, 'number' => 7, 'title' => 'Add feature', 'html_url' => 'https://github.com/acme/widgets/pull/7',
    ])]);

    $node = new GitHubCreatePullRequestNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'access_token' => 'gh-test', 'repo' => 'acme/widgets', 'title' => 'Add feature', 'head' => 'feature', 'base' => 'main',
    ], []);

    expect($output)->toBe([
        'id' => 11, 'number' => 7, 'title' => 'Add feature', 'url' => 'https://github.com/acme/widgets/pull/7',
    ]);
});

it('lists commits and omits unset optional filters', function () {
    Http::fake(['api.github.com/repos/acme/widgets/commits*' => Http::response([['sha' => 'abc123']])]);

    $node = new GitHubListCommitsNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'gh-test', 'repo' => 'acme/widgets'], []);

    expect($output['commits'])->toBe([['sha' => 'abc123']]);
    Http::assertSent(function ($request) {
        return ! isset($request['sha']) && ! isset($request['path']) && $request['per_page'] == 30;
    });
});

const GH_BASE = ['access_token' => 'ghp-test', 'repo' => 'acme/widgets'];

/**
 * @param  class-string  $class
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function runGitHub(string $class, array $config = []): array
{
    return app("App\\Nodes\\Integrations\\GitHub\\{$class}")->execute(Run::factory()->create(), [...GH_BASE, ...$config], []);
}

dataset('github actions', [
    'get issue' => ['GitHubGetIssueNode', ['issue_number' => 5], 'GET', '/repos/acme/widgets/issues/5', []],
    'update issue' => ['GitHubUpdateIssueNode', ['issue_number' => 5, 'title' => 'New', 'state' => 'closed', 'labels' => ['bug'], 'body' => ' '], 'PATCH', '/repos/acme/widgets/issues/5', ['title' => 'New', 'state' => 'closed', 'labels' => ['bug']]],
    'add labels' => ['GitHubAddLabelsNode', ['issue_number' => 5, 'labels' => ['bug', 'help wanted']], 'POST', '/repos/acme/widgets/issues/5/labels', ['labels' => ['bug', 'help wanted']]],
    'remove label' => ['GitHubRemoveLabelNode', ['issue_number' => 5, 'label' => 'good first issue'], 'DELETE', '/repos/acme/widgets/issues/5/labels/good%20first%20issue', []],
    'add assignees' => ['GitHubAddAssigneesNode', ['issue_number' => 5, 'assignees' => ['octocat']], 'POST', '/repos/acme/widgets/issues/5/assignees', ['assignees' => ['octocat']]],
    'issue comments' => ['GitHubListIssueCommentsNode', ['issue_number' => 5], 'GET', '/repos/acme/widgets/issues/5/comments', []],
    'update comment' => ['GitHubUpdateCommentNode', ['comment_id' => 9, 'body' => 'edited'], 'PATCH', '/repos/acme/widgets/issues/comments/9', ['body' => 'edited']],
    'delete comment' => ['GitHubDeleteCommentNode', ['comment_id' => 9], 'DELETE', '/repos/acme/widgets/issues/comments/9', []],
    'get pull request' => ['GitHubGetPullRequestNode', ['pull_number' => 3], 'GET', '/repos/acme/widgets/pulls/3', []],
    'update pull request' => ['GitHubUpdatePullRequestNode', ['pull_number' => 3, 'state' => 'closed'], 'PATCH', '/repos/acme/widgets/pulls/3', ['state' => 'closed']],
    'merge pull request' => ['GitHubMergePullRequestNode', ['pull_number' => 3, 'merge_method' => 'squash', 'commit_title' => 'Ship it'], 'PUT', '/repos/acme/widgets/pulls/3/merge', ['merge_method' => 'squash', 'commit_title' => 'Ship it']],
    'pull request files' => ['GitHubListPullRequestFilesNode', ['pull_number' => 3], 'GET', '/repos/acme/widgets/pulls/3/files', []],
    'approve pull request' => ['GitHubCreatePullRequestReviewNode', ['pull_number' => 3, 'event' => 'APPROVE'], 'POST', '/repos/acme/widgets/pulls/3/reviews', ['event' => 'APPROVE']],
    'request reviewers' => ['GitHubRequestReviewersNode', ['pull_number' => 3, 'reviewers' => ['octocat']], 'POST', '/repos/acme/widgets/pulls/3/requested_reviewers', ['reviewers' => ['octocat']]],
    'get commit' => ['GitHubGetCommitNode', ['ref' => 'feature/x'], 'GET', '/repos/acme/widgets/commits/feature/x', []],
    'latest release' => ['GitHubGetLatestReleaseNode', [], 'GET', '/repos/acme/widgets/releases/latest', []],
    'star' => ['GitHubStarRepositoryNode', [], 'PUT', '/user/starred/acme/widgets', []],
    'unstar' => ['GitHubUnstarRepositoryNode', [], 'DELETE', '/user/starred/acme/widgets', []],
    'fork' => ['GitHubForkRepositoryNode', ['organization' => 'acme-fork', 'default_branch_only' => true], 'POST', '/repos/acme/widgets/forks', ['organization' => 'acme-fork', 'default_branch_only' => true]],
    'run workflow' => ['GitHubDispatchWorkflowNode', ['workflow' => 'deploy.yml', 'ref' => 'main', 'inputs' => ['env' => 'prod']], 'POST', '/repos/acme/widgets/actions/workflows/deploy.yml/dispatches', ['ref' => 'main', 'inputs' => ['env' => 'prod']]],
    'rerun workflow' => ['GitHubRerunWorkflowRunNode', ['run_id' => 77], 'POST', '/repos/acme/widgets/actions/runs/77/rerun', []],
    'own user' => ['GitHubGetUserNode', [], 'GET', '/user', []],
    'another user' => ['GitHubGetUserNode', ['username' => 'octocat'], 'GET', '/users/octocat', []],
    'search issues' => ['GitHubSearchIssuesNode', ['query' => 'repo:acme/widgets is:open', 'sort' => 'created'], 'GET', '/search/issues', ['q' => 'repo:acme/widgets is:open', 'sort' => 'created']],
    'search repositories' => ['GitHubSearchRepositoriesNode', ['query' => 'laravel', 'sort' => 'best-match'], 'GET', '/search/repositories', ['q' => 'laravel']],
    'create gist' => ['GitHubCreateGistNode', ['filename' => 'a.md', 'content' => 'hi', 'public' => true], 'POST', '/gists', ['public' => true, 'files' => ['a.md' => ['content' => 'hi']]]],
    'notifications' => ['GitHubListNotificationsNode', ['participating' => true], 'GET', '/notifications', ['participating' => 'true']],
    'release' => ['GitHubCreateReleaseNode', ['tag_name' => 'v1.0.0', 'generate_notes' => true, 'prerelease' => true], 'POST', '/repos/acme/widgets/releases', ['tag_name' => 'v1.0.0', 'generate_release_notes' => true, 'prerelease' => true]],
    'delete branch' => ['GitHubDeleteBranchNode', ['branch' => 'feature/old'], 'DELETE', '/repos/acme/widgets/git/refs/heads/feature/old', []],
]);

it('calls the right GitHub endpoint with the right fields', function (string $class, array $config, string $method, string $path, array $expected) {
    Http::fake(['api.github.com/*' => Http::response(['sha' => 'abc', 'items' => [], 'total_count' => 0], $method === 'DELETE' || $method === 'PUT' && $path !== '/repos/acme/widgets/pulls/3/merge' ? 204 : 200)]);

    runGitHub($class, $config);

    Http::assertSent(fn ($request) => $request->method() === $method
        && explode('?', $request->url())[0] === 'https://api.github.com'.$path
        && collect($expected)->every(fn ($value, $key) => $request[$key] == $value)
        && $request->hasHeader('Authorization', 'Bearer ghp-test')
        && $request->hasHeader('Accept', 'application/vnd.github+json'));
})->with('github actions');

it('leaves out an update field that was left empty and refuses an update that changes nothing', function () {
    Http::fake(['api.github.com/*' => Http::response([])]);

    runGitHub('GitHubUpdateIssueNode', ['issue_number' => 5, 'title' => 'Only title', 'body' => '', 'labels' => []]);
    Http::assertSent(fn ($request) => $request->data() === ['title' => 'Only title']);

    expect(fn () => runGitHub('GitHubUpdateIssueNode', ['issue_number' => 5]))->toThrow(InvalidArgumentException::class, 'at least one field');
    expect(fn () => runGitHub('GitHubUpdatePullRequestNode', ['pull_number' => 3]))->toThrow(InvalidArgumentException::class, 'at least one field');
});

it('wants a comment to request changes but not to approve', function () {
    Http::fake(['api.github.com/*' => Http::response([])]);

    runGitHub('GitHubCreatePullRequestReviewNode', ['pull_number' => 3, 'event' => 'APPROVE']);
    Http::assertSent(fn ($request) => $request['event'] === 'APPROVE');

    expect(fn () => runGitHub('GitHubCreatePullRequestReviewNode', ['pull_number' => 3, 'event' => 'REQUEST_CHANGES']))
        ->toThrow(InvalidArgumentException::class, 'write a comment');
});

it('refuses a repository that is not owner/name, since it goes in the URL', function (string $repo) {
    Http::fake();

    runGitHub('GitHubGetIssueNode', ['repo' => $repo, 'issue_number' => 1]);
})->with(['widgets', 'acme/widgets/../../user', 'acme/widgets?x=1', '', '../x'])->throws(InvalidArgumentException::class, 'owner/name');

it('also checks the repository on the older nodes', function () {
    Http::fake();

    runGitHub('GitHubListIssuesNode', ['repo' => 'acme/../admin']);
})->throws(InvalidArgumentException::class, 'owner/name');

it('reads a text file and decodes it', function () {
    Http::fake(['api.github.com/*' => Http::response(['type' => 'file', 'path' => 'docs/a.md', 'sha' => 's1', 'size' => 11, 'content' => base64_encode('hello नमस्ते')."\n", 'encoding' => 'base64', 'html_url' => 'https://github.com/x'])]);

    $output = runGitHub('GitHubGetFileContentNode', ['path' => 'docs/a b.md', 'branch' => 'dev']);

    expect($output['content'])->toBe('hello नमस्ते')->and($output['sha'])->toBe('s1');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/contents/docs/a%20b.md') && str_contains($request->url(), 'ref=dev'));
});

it('refuses to read a folder or a file too big for the contents api', function () {
    Http::fake(['api.github.com/*' => Http::sequence()->push([['name' => 'a'], ['name' => 'b']])->push(['type' => 'file', 'size' => 5000000, 'content' => '', 'encoding' => 'none'])]);

    expect(fn () => runGitHub('GitHubGetFileContentNode', ['path' => 'docs']))->toThrow(RuntimeException::class, 'folder');
    expect(fn () => runGitHub('GitHubGetFileContentNode', ['path' => 'big.bin']))->toThrow(RuntimeException::class, 'over 1 MB');
});

it('updates a file using the sha it finds, committing base64 content', function () {
    Http::fake([
        'api.github.com/repos/acme/widgets/contents/docs/a.md?*' => Http::response(['sha' => 'old-sha']),
        'api.github.com/repos/acme/widgets/contents/docs/a.md' => Http::response(['content' => ['path' => 'docs/a.md', 'sha' => 'new-sha'], 'commit' => ['sha' => 'c1', 'html_url' => 'https://github.com/c1']]),
    ]);

    $output = runGitHub('GitHubCreateOrUpdateFileNode', ['path' => 'docs/a.md', 'content' => 'new text', 'message' => 'Update', 'branch' => 'dev']);

    expect($output)->toMatchArray(['sha' => 'new-sha', 'commit_sha' => 'c1', 'created' => false]);
    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request['sha'] === 'old-sha' && $request['branch'] === 'dev' && base64_decode($request['content']) === 'new text' && $request['message'] === 'Update');
});

it('creates a file that does not exist yet, without a sha', function () {
    Http::fake(['api.github.com/repos/acme/widgets/contents/new.md' => fn ($request) => $request->method() === 'GET'
        ? Http::response(['message' => 'Not Found'], 404)
        : Http::response(['content' => ['path' => 'new.md', 'sha' => 's'], 'commit' => ['sha' => 'c2']], 201)]);

    $output = runGitHub('GitHubCreateOrUpdateFileNode', ['path' => 'new.md', 'content' => 'x', 'message' => 'Add']);

    expect($output['created'])->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'PUT' && ! isset($request['sha']));
});

it('does not hide a real error while looking for the file sha', function () {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401)]);

    runGitHub('GitHubCreateOrUpdateFileNode', ['path' => 'a.md', 'content' => 'x', 'message' => 'Add']);
})->throws(RuntimeException::class, 'Bad credentials');

it('deletes a file with the sha it finds, and says when there is no such file', function () {
    Http::fake(['api.github.com/repos/acme/widgets/contents/old.md' => fn ($request) => $request->method() === 'GET'
        ? Http::response(['sha' => 'old-sha'])
        : Http::response(['commit' => ['sha' => 'c3']])]);

    $output = runGitHub('GitHubDeleteFileNode', ['path' => 'old.md', 'message' => 'Remove']);

    expect($output)->toBe(['deleted' => true, 'path' => 'old.md', 'commit_sha' => 'c3']);
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request['sha'] === 'old-sha' && $request['message'] === 'Remove');
});

it('cannot delete a file that is not there', function () {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

    runGitHub('GitHubDeleteFileNode', ['path' => 'gone.md', 'message' => 'Remove']);
})->throws(RuntimeException::class, 'no file at that path to delete');

it('creates a branch from the default branch when none is chosen', function () {
    Http::fake([
        'api.github.com/repos/acme/widgets' => Http::response(['default_branch' => 'main']),
        'api.github.com/repos/acme/widgets/git/ref/heads/main' => Http::response(['object' => ['sha' => 'base-sha']]),
        'api.github.com/repos/acme/widgets/git/refs' => Http::response(['object' => ['sha' => 'base-sha']], 201),
    ]);

    $output = runGitHub('GitHubCreateBranchNode', ['name' => ' feature/login ']);

    expect($output)->toBe(['name' => 'feature/login', 'sha' => 'base-sha', 'from' => 'main']);
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['ref'] === 'refs/heads/feature/login' && $request['sha'] === 'base-sha');
});

it('creates a branch from a chosen branch without asking for the default one', function () {
    Http::fake([
        'api.github.com/repos/acme/widgets/git/ref/heads/develop' => Http::response(['object' => ['sha' => 'dev-sha']]),
        'api.github.com/repos/acme/widgets/git/refs' => Http::response(['object' => ['sha' => 'dev-sha']], 201),
    ]);

    runGitHub('GitHubCreateBranchNode', ['name' => 'hotfix', 'from' => 'develop']);

    Http::assertSentCount(2);
});

it('shows the GitHub error message', function () {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Pull Request is not mergeable'], 405)]);

    runGitHub('GitHubMergePullRequestNode', ['pull_number' => 3]);
})->throws(RuntimeException::class, 'Pull Request is not mergeable');

it('registers every new github node under the github category', function () {
    $registry = app(NodeRegistry::class);
    $count = 0;

    foreach (array_keys($registry->connectors()) as $type) {
        if (str_starts_with($type, 'github_')) {
            expect($registry->resolve($type)->category())->toBe('github');
            $count++;
        }
    }

    expect($count)->toBe(49);
});

it('marks destructive github nodes as destructive', function () {
    foreach (['DeleteFile', 'DeleteBranch', 'DeleteComment'] as $name) {
        expect(app("App\\Nodes\\Integrations\\GitHub\\GitHub{$name}Node")->effect([])->value)->toBe('destructive');
    }

    expect(app(GitHubMergePullRequestNode::class)->effect([])->value)->toBe('external')
        ->and(app(GitHubGetIssueNode::class)->effect([])->value)->toBe('read');
});
