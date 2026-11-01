<?php

use App\Enums\Connectors\ConnectorAuthType;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Nodes\NodeCategory;
use App\Models\User;
use App\Services\Connectors\ConnectorCredentialResolver;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Connectors\SmtpMailer;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

/**
 * An account of a seeded messaging connector, holding the fields a member typed.
 *
 * @param  array<string, mixed>  $data
 */
function messagingAccount(string $key, array $data): ConnectorCredential
{
    test()->syncSetup('connectors');
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->where('key', $key)->firstOrFail())
        ->create(['data' => $data, 'created_by' => $owner->id]);
}

describe('messaging connectors', function () {
    it('seeds the four connectors with the form a member fills in, and a node category each', function () {
        $this->syncSetup('connectors', 'app-node-categories');

        $fields = fn (string $key): array => collect(Connector::query()->where('key', $key)->firstOrFail()->fields)->keyBy('name')->all();

        expect(Connector::query()->where('key', 'telegram')->firstOrFail()->auth_type)->toBe(ConnectorAuthType::ApiKey)
            ->and(Connector::query()->where('key', 'twilio')->firstOrFail()->auth_type)->toBe(ConnectorAuthType::BasicAuth)
            ->and($fields('telegram'))->toHaveKeys(['api_key'])
            ->and($fields('telegram')['api_key'])->toMatchArray(['label' => 'Bot token', 'secret' => true, 'required' => true])
            ->and($fields('discord'))->toHaveKeys(['api_key', 'application_id', 'public_key'])
            ->and($fields('discord')['application_id']['required'])->toBeFalse()
            ->and($fields('twilio'))->toHaveKeys(['account_sid', 'api_key'])
            ->and($fields('smtp'))->toHaveKeys(['host', 'port', 'encryption', 'username', 'api_key', 'from_address', 'from_name'])
            ->and($fields('smtp')['host']['required'])->toBeTrue()
            ->and(NodeCategory::query()->whereIn('slug', ['telegram', 'discord', 'twilio', 'smtp'])->where('kind', 'app')->count())->toBe(4);

        foreach (['telegram', 'discord', 'twilio', 'smtp'] as $key) {
            $connector = Connector::query()->where('key', $key)->firstOrFail();

            expect($connector->isOAuth())->toBeFalse();
        }
    });

    it('has a connector and a category for every connected node of the four families', function () {
        $this->syncSetup('connectors', 'app-node-categories');

        $registry = app(NodeRegistry::class);
        $categories = collect(array_keys($registry->connectors()))
            ->map(fn (string $type): string => $registry->resolve($type)->category())
            ->filter(fn (string $category): bool => in_array($category, ['telegram', 'discord', 'twilio', 'smtp'], true))
            ->unique()->sort()->values()->all();

        expect($categories)->toBe(['discord', 'smtp', 'telegram', 'twilio'])
            ->and(NodeCategory::query()->whereIn('slug', $categories)->count())->toBe(4);
    });

    it('reads the whole stored account for the apps whose login is more than one token', function () {
        $account = messagingAccount('twilio', ['account_sid' => 'AC'.str_repeat('1', 32), 'api_key' => 'token']);
        $resolver = app(ConnectorCredentialResolver::class);

        expect($resolver->credentialData('twilio', $account->workspace_id, null, ['credential_id' => $account->id]))->toBe(['account_sid' => 'AC'.str_repeat('1', 32), 'api_key' => 'token'])
            ->and($account->fresh()->last_used_at)->not->toBeNull();

        $other = messagingAccount('twilio', ['account_sid' => 'AC'.str_repeat('2', 32), 'api_key' => 'other']);

        expect(fn () => $resolver->credentialData('twilio', $account->workspace_id, null, ['credential_id' => $other->id]))->toThrow(RuntimeException::class, 'not found in this workspace')
            ->and($resolver->credentialData('twilio', $account->workspace_id, null, ['credential_id' => $account->id], markUsed: false))->toHaveKey('account_sid');
    });
});

describe('saving a messaging connection', function () {
    it('refuses one that is missing a field its form requires, and says which', function () {
        [$workspace, $owner] = ownerWorkspaceForConnectors();
        $this->syncSetup('connectors');
        $twilio = Connector::query()->where('key', 'twilio')->firstOrFail();
        $smtp = Connector::query()->where('key', 'smtp')->firstOrFail();
        Passport::actingAs($owner);

        $missing = $this->postJson("/api/v1/workspaces/{$workspace->id}/connector-credentials", ['connector_id' => $twilio->id, 'name' => 'Twilio', 'data' => ['account_sid' => 'AC'.str_repeat('1', 32), 'api_key' => '  ']]);

        $missing->assertStatus(422);
        expect($missing->getContent())->toContain('Auth token')->not->toContain('Account SID');
        expect(ConnectorCredential::query()->where('name', 'Twilio')->exists())->toBeFalse();

        $this->postJson("/api/v1/workspaces/{$workspace->id}/connector-credentials", ['connector_id' => $twilio->id, 'name' => 'Twilio', 'data' => ['account_sid' => 'AC'.str_repeat('1', 32), 'api_key' => 'token']])->assertCreated();

        // An optional field (the mail password, the bot's application id) may be left out.
        $this->postJson("/api/v1/workspaces/{$workspace->id}/connector-credentials", ['connector_id' => $smtp->id, 'name' => 'Mail', 'data' => ['host' => 'smtp.example.test', 'port' => 587, 'from_address' => 'hello@acme.test']])->assertCreated();

        $credential = ConnectorCredential::query()->where('name', 'Mail')->firstOrFail();
        $this->patchJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$credential->id}", ['data' => ['port' => 587]])->assertStatus(422);
        $this->patchJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$credential->id}", ['name' => 'Mail renamed'])->assertOk();
    });
});

describe('messaging connection checks', function () {
    it('asks Telegram, Discord and Twilio who the keys belong to', function () {
        Http::swap(new Factory);
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['id' => 1, 'username' => 'acme_bot']]),
            'discord.com/*' => Http::response(['id' => '1', 'username' => 'Acme Bot']),
            'api.twilio.com/*' => Http::response(['friendly_name' => 'Acme Inc', 'status' => 'active']),
        ]);
        $tester = app(ConnectorCredentialTester::class);

        $telegram = $tester->test(messagingAccount('telegram', ['api_key' => '123:ABC']));
        $discord = $tester->test(messagingAccount('discord', ['api_key' => 'bot-token']));
        $twilio = $tester->test(messagingAccount('twilio', ['account_sid' => 'AC'.str_repeat('1', 32), 'api_key' => 'token']));

        expect($telegram)->toMatchArray(['ok' => true, 'account' => '@acme_bot'])
            ->and($discord)->toMatchArray(['ok' => true, 'account' => 'Acme Bot'])
            ->and($twilio)->toMatchArray(['ok' => true, 'account' => 'Acme Inc']);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.telegram.org/bot123:ABC/getMe');
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bot bot-token'));
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('AC'.str_repeat('1', 32).':token')));
    });

    it('says when a bot token or an auth token is refused, and what is missing', function () {
        Http::swap(new Factory);
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized'], 401),
            'discord.com/*' => Http::response(['message' => '401: Unauthorized', 'code' => 0], 401),
            'api.twilio.com/*' => Http::response(['code' => 20003, 'message' => 'Authenticate'], 401),
        ]);
        $tester = app(ConnectorCredentialTester::class);

        expect($tester->test(messagingAccount('telegram', ['api_key' => 'bad']))['ok'])->toBeFalse()
            ->and($tester->test(messagingAccount('discord', ['api_key' => 'bad']))['message'])->toContain('rejected')
            ->and($tester->test(messagingAccount('twilio', ['account_sid' => 'AC'.str_repeat('1', 32), 'api_key' => 'bad']))['ok'])->toBeFalse()
            ->and($tester->test(messagingAccount('twilio', ['api_key' => 'token-only']))['message'])->toContain('both the account SID and the auth token');
    });

    it('checks that the mail server accepts the login, and says what it said when it does not', function () {
        $this->partialMock(SmtpMailer::class, fn ($mailer) => $mailer->shouldReceive('verify')->twice()->andReturnUsing(function (array $account): void {
            if (($account['api_key'] ?? '') === 'wrong') {
                throw new Exception('Expected response code "250" but got code "535", with message "535 Authentication failed".');
            }
        }));
        $tester = app(ConnectorCredentialTester::class);
        $settings = ['host' => 'smtp.example.test', 'port' => 587, 'username' => 'u', 'from_address' => 'hello@acme.test'];

        $good = $tester->test(messagingAccount('smtp', [...$settings, 'api_key' => 'right']));
        $bad = $tester->test(messagingAccount('smtp', [...$settings, 'api_key' => 'wrong']));

        expect($good)->toMatchArray(['ok' => true, 'account' => 'hello@acme.test'])
            ->and($bad['ok'])->toBeFalse()
            ->and($bad['message'])->toContain('did not accept these settings')->toContain('535');
    });
});
