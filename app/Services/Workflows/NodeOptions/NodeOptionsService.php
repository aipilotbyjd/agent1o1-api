<?php

namespace App\Services\Workflows\NodeOptions;

use App\Exceptions\ConnectorException;
use App\Models\Connectors\Connector;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Connectors\ConnectorCredentialResolver;
use App\Services\Workflows\NodeOptions\Sources\ActiveCampaignOptions;
use App\Services\Workflows\NodeOptions\Sources\AirtableOptions;
use App\Services\Workflows\NodeOptions\Sources\AsanaOptions;
use App\Services\Workflows\NodeOptions\Sources\BrevoOptions;
use App\Services\Workflows\NodeOptions\Sources\ClickUpOptions;
use App\Services\Workflows\NodeOptions\Sources\DiscordOptions;
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
use App\Services\Workflows\NodeOptions\Sources\HubSpotOptions;
use App\Services\Workflows\NodeOptions\Sources\JiraOptions;
use App\Services\Workflows\NodeOptions\Sources\KitOptions;
use App\Services\Workflows\NodeOptions\Sources\KlaviyoOptions;
use App\Services\Workflows\NodeOptions\Sources\LinearOptions;
use App\Services\Workflows\NodeOptions\Sources\MailchimpOptions;
use App\Services\Workflows\NodeOptions\Sources\MailgunOptions;
use App\Services\Workflows\NodeOptions\Sources\MondayOptions;
use App\Services\Workflows\NodeOptions\Sources\NotionOptions;
use App\Services\Workflows\NodeOptions\Sources\OutlookOptions;
use App\Services\Workflows\NodeOptions\Sources\PipedriveOptions;
use App\Services\Workflows\NodeOptions\Sources\PostmarkOptions;
use App\Services\Workflows\NodeOptions\Sources\ResendOptions;
use App\Services\Workflows\NodeOptions\Sources\SalesforceOptions;
use App\Services\Workflows\NodeOptions\Sources\SendGridOptions;
use App\Services\Workflows\NodeOptions\Sources\ShopifyOptions;
use App\Services\Workflows\NodeOptions\Sources\SlackOptions;
use App\Services\Workflows\NodeOptions\Sources\StripeOptions;
use App\Services\Workflows\NodeOptions\Sources\TrelloOptions;
use App\Services\Workflows\NodeOptions\Sources\TwilioOptions;
use App\Services\Workflows\NodeOptions\Sources\TypeformOptions;
use App\Services\Workflows\NodeOptions\Sources\WorkspaceOptions;
use App\Services\Workflows\NodeOptions\Sources\YouTubeOptions;
use App\Services\Workflows\NodeOptions\Sources\ZohoBooksOptions;
use App\Services\Workflows\NodeOptions\Sources\ZohoCampaignsOptions;
use App\Services\Workflows\NodeOptions\Sources\ZohoCliqOptions;
use App\Services\Workflows\NodeOptions\Sources\ZohoDeskOptions;
use App\Services\Workflows\NodeOptions\Sources\ZohoMailOptions;
use App\Services\Workflows\NodeOptions\Sources\ZohoOptions;
use App\Services\Workflows\NodeOptions\Sources\ZohoProjectsOptions;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

/**
 * Fills a node field's dropdown in the workflow editor: the spreadsheets,
 * tabs, channels, repos, labels… the member would otherwise have to copy
 * IDs for. The field must declare `x-options` in its node's
 * `configSchema()` (see `App\Nodes\Support\Field::dynamic()`); the source
 * is read from the schema, never from the request, so a caller can only
 * load lists some node actually offers.
 *
 * Connected-account sources use the same credential the node would run
 * with: pinned `credential_id`, else the member's personal default, else
 * the workspace's team default (`ConnectorCredentialResolver`). A raw
 * `access_token` in the request is never used.
 */
class NodeOptionsService
{
    /**
     * Every registered source class. `NodeConfigSchemaTest` checks that each
     * `x-options.source` a node declares is answered by one of these.
     *
     * @var list<class-string<OptionsSource>>
     */
    public const array SOURCES = [
        SlackOptions::class,
        DiscordOptions::class,
        TwilioOptions::class,
        TypeformOptions::class,
        StripeOptions::class,
        ShopifyOptions::class,
        MailchimpOptions::class,
        KitOptions::class,
        ActiveCampaignOptions::class,
        KlaviyoOptions::class,
        BrevoOptions::class,
        SendGridOptions::class,
        ResendOptions::class,
        PostmarkOptions::class,
        MailgunOptions::class,
        TrelloOptions::class,
        AsanaOptions::class,
        ClickUpOptions::class,
        AirtableOptions::class,
        MondayOptions::class,
        HubSpotOptions::class,
        PipedriveOptions::class,
        SalesforceOptions::class,
        ZohoOptions::class,
        ZohoDeskOptions::class,
        ZohoBooksOptions::class,
        ZohoProjectsOptions::class,
        ZohoCampaignsOptions::class,
        ZohoMailOptions::class,
        ZohoCliqOptions::class,
        GmailOptions::class,
        GitHubOptions::class,
        GoogleDriveOptions::class,
        GoogleSheetsOptions::class,
        GoogleDocsOptions::class,
        GoogleCalendarOptions::class,
        GoogleTasksOptions::class,
        GoogleFormsOptions::class,
        GoogleSlidesOptions::class,
        GoogleAnalyticsOptions::class,
        GoogleSearchConsoleOptions::class,
        YouTubeOptions::class,
        OutlookOptions::class,
        NotionOptions::class,
        LinearOptions::class,
        JiraOptions::class,
        WorkspaceOptions::class,
    ];

    /**
     * Source prefixes that read the workspace or the app itself (timezones)
     * and need no connected account.
     */
    private const array INTERNAL_PREFIXES = ['workspace', 'ai', 'system'];

    /**
     * Source key => class, built on first use.
     *
     * @var array<string, class-string<OptionsSource>>|null
     */
    private ?array $classesBySource = null;

    public function __construct(
        private readonly NodeRegistry $registry,
        private readonly ConnectorCredentialResolver $credentials,
    ) {}

    /**
     * @param  array<string, mixed>  $config  the node's current (unsaved) config
     *
     * @throws ValidationException when the field has no options, or a field it depends on isn't usable yet
     * @throws ConnectorException when no account is connected, or the provider refuses
     */
    public function load(Workspace $workspace, User $user, string $type, string $field, array $config, ?string $search = null, ?string $cursor = null): NodeOptionsPage
    {
        $node = $this->registry->describe($type)
            ?? throw ValidationException::withMessages(['type' => "No node is registered for type [{$type}]."]);

        $properties = $node['config_schema']['properties'] ?? [];
        $options = $properties[$field]['x-options'] ?? null;

        if (! is_array($options) || ! is_string($options['source'] ?? null)) {
            throw ValidationException::withMessages(['field' => "The [{$field}] field of [{$type}] has no options to load."]);
        }

        $source = $options['source'];
        $this->assertDependenciesUsable($properties, $options['depends_on'] ?? [], $config);

        $token = $this->isInternal($source) ? null : $this->token($node['category'], $workspace, $user, $config);

        $query = new NodeOptionsQuery(
            workspace: $workspace,
            user: $user,
            config: $config,
            token: $token,
            search: $search,
            cursor: $cursor,
            credentialData: $token === null ? [] : $this->credentialData($node['category'], $workspace, $user, $config),
        );

        return app($this->classFor($source))->load($source, $query);
    }

    /**
     * Every source key the registered classes answer.
     *
     * @return list<string>
     */
    public function sourceKeys(): array
    {
        return array_keys($this->classesBySource());
    }

    /**
     * A dependency must be set, and set to a literal: a `{{template}}` only
     * has a value at run time.
     *
     * @param  array<string, array<string, mixed>>  $properties
     * @param  list<string>  $dependsOn
     * @param  array<string, mixed>  $config
     *
     * @throws ValidationException
     */
    private function assertDependenciesUsable(array $properties, array $dependsOn, array $config): void
    {
        foreach ($dependsOn as $dependency) {
            $value = $config[$dependency] ?? null;
            $title = $properties[$dependency]['title'] ?? Str::headline($dependency);

            if (! is_scalar($value) || trim((string) $value) === '') {
                throw ValidationException::withMessages(["config.{$dependency}" => "Choose a {$title} first."]);
            }

            if (str_contains((string) $value, '{{')) {
                throw ValidationException::withMessages(["config.{$dependency}" => "{$title} is set from an earlier step, so this list can't be loaded. Type a value instead."]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws ConnectorException
     */
    private function token(string $connectorKey, Workspace $workspace, User $user, array $config): string
    {
        $credentialId = $config['credential_id'] ?? null;

        // A templated account can't be resolved here: fall back to the default.
        if (! is_string($credentialId) || str_contains($credentialId, '{{')) {
            $config['credential_id'] = null;
        }

        try {
            return $this->credentials->accessToken($connectorKey, $workspace->id, $user->id, $config, allowRawToken: false);
        } catch (ConnectorException $e) {
            throw $e;
        } catch (RuntimeException) {
            $app = Connector::where('key', $connectorKey)->value('name') ?? Str::headline($connectorKey);

            throw new ConnectorException("Connect a {$app} account (or choose one in Account) to load this list.");
        }
    }

    /**
     * The account's stored fields (a Twilio account sid, a bot's application
     * id), read only after `token()` has found the account.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function credentialData(string $connectorKey, Workspace $workspace, User $user, array $config): array
    {
        $credentialId = $config['credential_id'] ?? null;

        if (! is_string($credentialId) || str_contains($credentialId, '{{')) {
            $config['credential_id'] = null;
        }

        try {
            return $this->credentials->credentialData($connectorKey, $workspace->id, $user->id, $config, markUsed: false);
        } catch (RuntimeException) {
            return [];
        }
    }

    private function isInternal(string $source): bool
    {
        return in_array(Str::before($source, '.'), self::INTERNAL_PREFIXES, true);
    }

    /**
     * @return class-string<OptionsSource>
     */
    private function classFor(string $source): string
    {
        return $this->classesBySource()[$source]
            ?? throw new LogicException("No options source is registered for [{$source}].");
    }

    /**
     * @return array<string, class-string<OptionsSource>>
     */
    private function classesBySource(): array
    {
        if ($this->classesBySource !== null) {
            return $this->classesBySource;
        }

        $map = [];

        foreach (self::SOURCES as $class) {
            foreach (app($class)->sources() as $source) {
                if (isset($map[$source])) {
                    throw new LogicException("Options source [{$source}] is registered by both [{$map[$source]}] and [{$class}].");
                }

                $map[$source] = $class;
            }
        }

        return $this->classesBySource = $map;
    }
}
