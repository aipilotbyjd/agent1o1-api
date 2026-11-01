<?php

use App\Enums\Connectors\ConnectorAuthType;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Connectors\OAuthConnectorState;
use App\Models\Nodes\NodeCategory;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Connectors\OAuthConnectorFlowService;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * @return array{0: User, 1: Workspace}
 */
function connectorOwner(): array
{
    $owner = User::factory()->create();

    return [$owner, app(WorkspaceService::class)->create($owner, ['name' => 'Acme'])];
}

/**
 * An account of the connector, holding a token.
 */
function testAccount(string $key, string $name): ConnectorCredential
{
    [$owner, $workspace] = connectorOwner();

    return ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', $key) ?? Connector::factory()->create(['key' => $key, 'name' => $name]))
        ->create(['data' => ['access_token' => 'tok'], 'created_by' => $owner->id]);
}

it('seeds the linear and jira connectors and node categories', function () {
    $this->syncSetup('connectors', 'app-node-categories');

    $linear = Connector::query()->where('key', 'linear')->firstOrFail();
    $jira = Connector::query()->where('key', 'jira')->firstOrFail();

    expect($linear->auth_type)->toBe(ConnectorAuthType::OAuth2)
        ->and($linear->oauth)->toMatchArray([
            'authorize_url' => 'https://linear.app/oauth/authorize',
            'token_url' => 'https://api.linear.app/oauth/token',
            'scope_separator' => ',',
            'scopes' => ['read', 'write', 'issues:create', 'comments:create'],
        ])
        ->and($jira->auth_type)->toBe(ConnectorAuthType::OAuth2)
        ->and($jira->oauth)->toMatchArray([
            'authorize_url' => 'https://auth.atlassian.com/authorize',
            'token_url' => 'https://auth.atlassian.com/oauth/token',
            'token_auth' => 'json',
            'authorize_params' => ['audience' => 'api.atlassian.com', 'prompt' => 'consent'],
        ])
        ->and($jira->oauth['scopes'])->toContain('offline_access', 'read:jira-work', 'write:jira-work', 'read:servicedesk-request')
        ->and(NodeCategory::query()->whereIn('slug', ['linear', 'jira'])->where('kind', 'app')->count())->toBe(2);
});

it('has a connector and a category for every connected node of both families', function () {
    $this->syncSetup('connectors', 'app-node-categories');

    $registry = app(NodeRegistry::class);
    $categories = collect(array_keys($registry->connectors()))
        ->map(fn (string $type): string => $registry->resolve($type)->category())
        ->filter(fn (string $category): bool => in_array($category, ['linear', 'jira'], true))
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($categories)->toBe(['jira', 'linear']);
});

it('is off until its client credentials are set', function () {
    $this->syncSetup('connectors');

    config(['services.linear.client_id' => null, 'services.jira.client_id' => 'x', 'services.jira.client_secret' => 'y']);

    expect(Connector::query()->where('key', 'linear')->firstOrFail()->isConfigured())->toBeFalse()
        ->and(Connector::query()->where('key', 'jira')->firstOrFail()->isConfigured())->toBeTrue();
});

it('sends the person to linear with comma-separated scopes', function () {
    $this->syncSetup('connectors');
    config(['services.linear.client_id' => 'lin-id', 'services.linear.client_secret' => 'lin-secret']);
    [$owner, $workspace] = connectorOwner();

    $url = app(OAuthConnectorFlowService::class)->initiate($workspace, $owner, Connector::query()->where('key', 'linear')->firstOrFail(), 'Linear', 'https://app.test/cb')['authorize_url'];
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith('https://linear.app/oauth/authorize?')
        ->and($query)->toMatchArray(['client_id' => 'lin-id', 'response_type' => 'code', 'scope' => 'read,write,issues:create,comments:create']);
});

it('sends the person to atlassian with the audience and a consent screen', function () {
    $this->syncSetup('connectors');
    config(['services.jira.client_id' => 'atl-id', 'services.jira.client_secret' => 'atl-secret']);
    [$owner, $workspace] = connectorOwner();

    $url = app(OAuthConnectorFlowService::class)->initiate($workspace, $owner, Connector::query()->where('key', 'jira')->firstOrFail(), 'Jira', 'https://app.test/cb')['authorize_url'];
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith('https://auth.atlassian.com/authorize?')
        ->and($query)->toMatchArray(['audience' => 'api.atlassian.com', 'prompt' => 'consent', 'client_id' => 'atl-id', 'response_type' => 'code'])
        ->and(explode(' ', $query['scope']))->toContain('offline_access', 'read:jira-work');
});

it('trades the code for atlassian tokens with a JSON body, and refreshes the same way', function () {
    $this->syncSetup('connectors');
    config(['services.jira.client_id' => 'atl-id', 'services.jira.client_secret' => 'atl-secret']);
    $connector = Connector::query()->where('key', 'jira')->firstOrFail();
    [$owner, $workspace] = connectorOwner();

    Http::fake(['auth.atlassian.com/oauth/token' => Http::sequence()
        ->push(['access_token' => 'atl-1', 'refresh_token' => 'rt-1', 'expires_in' => 3600, 'scope' => 'read:jira-work', 'token_type' => 'Bearer'])
        ->push(['access_token' => 'atl-2', 'refresh_token' => 'rt-2', 'expires_in' => 3600])]);

    OAuthConnectorState::create([
        'workspace_id' => $workspace->id, 'user_id' => $owner->id, 'connector_id' => $connector->id,
        'state' => 'st', 'name' => 'Jira', 'redirect_uri' => 'https://app.test/cb', 'expires_at' => now()->addMinutes(10),
    ]);

    $credential = app(OAuthConnectorFlowService::class)->handleCallback('st', 'code-1');
    $firstTokens = $credential->data;
    $refreshed = app(OAuthConnectorFlowService::class)->refresh($credential);

    expect($firstTokens)->toMatchArray(['access_token' => 'atl-1', 'refresh_token' => 'rt-1'])
        ->and($refreshed->data)->toMatchArray(['access_token' => 'atl-2', 'refresh_token' => 'rt-2']);

    $sent = collect(Http::recorded()->all())->map(fn (array $pair): Request => $pair[0])->values();

    expect($sent[0]->isJson())->toBeTrue()
        ->and($sent[0]->data())->toBe(['client_id' => 'atl-id', 'client_secret' => 'atl-secret', 'code' => 'code-1', 'redirect_uri' => 'https://app.test/cb', 'grant_type' => 'authorization_code'])
        ->and($sent[1]->data())->toBe(['client_id' => 'atl-id', 'client_secret' => 'atl-secret', 'refresh_token' => 'rt-1', 'grant_type' => 'refresh_token']);
});

it('still trades the other connectors\' codes in a form body', function () {
    $this->syncSetup('connectors');
    config(['services.linear.client_id' => 'lin-id', 'services.linear.client_secret' => 'lin-secret']);
    $connector = Connector::query()->where('key', 'linear')->firstOrFail();
    [$owner, $workspace] = connectorOwner();

    Http::fake(['api.linear.app/oauth/token' => Http::response(['access_token' => 'lin-1', 'token_type' => 'Bearer', 'expires_in' => 86399])]);
    OAuthConnectorState::create([
        'workspace_id' => $workspace->id, 'user_id' => $owner->id, 'connector_id' => $connector->id,
        'state' => 'st', 'name' => 'Linear', 'redirect_uri' => 'https://app.test/cb', 'expires_at' => now()->addMinutes(10),
    ]);

    app(OAuthConnectorFlowService::class)->handleCallback('st', 'code-1');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.linear.app/oauth/token'
        && $request->isForm()
        && $request->data() === ['client_id' => 'lin-id', 'client_secret' => 'lin-secret', 'code' => 'code-1', 'redirect_uri' => 'https://app.test/cb', 'grant_type' => 'authorization_code']);
});

describe('checking a connection', function () {
    it('asks Linear who the token belongs to', function () {
        Http::fake(['api.linear.app/graphql' => Http::response(['data' => ['viewer' => ['name' => 'Ada', 'email' => 'ada@acme.com']]])]);

        expect(app(ConnectorCredentialTester::class)->test(testAccount('linear', 'Linear')))->toMatchArray(['ok' => true, 'account' => 'ada@acme.com']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer tok') && str_contains((string) $request['query'], 'viewer'));
    });

    it('treats a Linear 200 with errors as a rejected token', function () {
        Http::fake(['api.linear.app/graphql' => Http::response(['errors' => [['message' => 'Authentication required']]])]);

        expect(app(ConnectorCredentialTester::class)->test(testAccount('linear', 'Linear')))->toMatchArray(['ok' => false]);
    });

    it('lists the Jira sites the Atlassian account can open', function () {
        Http::fake(['api.atlassian.com/oauth/token/accessible-resources' => Http::response([['id' => 'c1', 'name' => 'acme'], ['id' => 'c2', 'name' => 'beta']])]);

        expect(app(ConnectorCredentialTester::class)->test(testAccount('jira', 'Jira')))->toMatchArray(['ok' => true, 'account' => 'acme, beta']);
    });

    it('fails an Atlassian account that has no Jira site, or a rejected one', function () {
        Http::fake(['api.atlassian.com/oauth/token/accessible-resources' => Http::response([])]);
        expect(app(ConnectorCredentialTester::class)->test(testAccount('jira', 'Jira')))->toMatchArray(['ok' => false]);

        Http::swap(new Factory);
        Http::fake(['api.atlassian.com/oauth/token/accessible-resources' => Http::response(['message' => 'Unauthorized'], 401)]);
        expect(app(ConnectorCredentialTester::class)->test(testAccount('jira', 'Jira'))['ok'])->toBeFalse();
    });
});
