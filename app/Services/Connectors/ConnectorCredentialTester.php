<?php

namespace App\Services\Connectors;

use App\Exceptions\ConnectorException;
use App\Models\Connectors\ConnectorCredential;
use App\Nodes\Integrations\Shopify\AbstractShopifyNode;
use App\Nodes\Integrations\ActiveCampaign\AbstractActiveCampaignNode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Checks a connection really works: it gets a usable access token
 * (refreshing an OAuth token that needs it, exactly as a run would) and
 * then asks the provider who that token belongs to. A connector with no
 * known identity endpoint is judged on the token alone.
 */
class ConnectorCredentialTester
{
    private const int TIMEOUT_SECONDS = 10;

    /**
     * Apps connected with several fields, or a key that must be sent their own way.
     *
     * @var list<string>
     */
    private const array MANUAL = ['telegram', 'discord', 'twilio', 'smtp', 'typeform', 'mailchimp', 'kit', 'activecampaign', 'klaviyo', 'brevo', 'sendgrid', 'resend', 'postmark', 'mailgun', 'trello', 'asana', 'clickup', 'monday', 'airtable', 'hubspot', 'pipedrive', 'salesforce', 'zoho', 'zoho_desk', 'zoho_books', 'zoho_projects', 'zoho_campaigns', 'zoho_mail', 'zoho_cliq', 'stripe', 'shopify'];

    public function __construct(private readonly ConnectorTokens $tokens) {}

    /**
     * Runs the check and records it on the credential, so the result (and
     * whose account it is) outlives the request that asked.
     *
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     */
    public function test(ConnectorCredential $credential): array
    {
        $result = $this->check($credential);

        $credential->forceFill([
            'last_tested_at' => $result['tested_at'],
            'last_test_ok' => $result['ok'],
            'last_test_message' => Str::limit($result['message'], 497),
            'account_label' => $result['account'] ?? $credential->account_label,
        ])->saveQuietly();

        return $result;
    }

    /**
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     */
    private function check(ConnectorCredential $credential): array
    {
        $credential->loadMissing('connector');
        $appName = $credential->connector?->name ?? 'This app';

        // These log in with several fields, so there is no one token to check.
        if (in_array($credential->connector?->key, self::MANUAL, true)) {
            try {
                return $this->checkManual($credential->connector->key, (array) $credential->data, $appName);
            } catch (ConnectionException) {
                return $this->result(false, "Couldn't reach {$appName}. Try again in a moment.");
            }
        }

        try {
            $token = $this->tokens->accessToken($credential, markUsed: false);
        } catch (ConnectorException $e) {
            return $this->result(false, $e->getMessage());
        }

        try {
            return $this->checkIdentity($credential->connector?->key ?? '', $token, $appName);
        } catch (ConnectionException) {
            return $this->result(false, "Couldn't reach {$appName}. Try again in a moment.");
        }
    }

    /**
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     *
     * @throws ConnectionException
     */
    private function checkIdentity(string $connectorKey, string $token, string $appName): array
    {
        $request = Http::withToken($token)->acceptJson()->timeout(self::TIMEOUT_SECONDS);

        if ($connectorKey === 'github') {
            $response = $request->get('https://api.github.com/user');

            return $this->fromResponse($response, $appName, $response->json('login'));
        }

        if ($connectorKey === 'slack') {
            $response = $request->post('https://slack.com/api/auth.test');

            if ($response->successful() && $response->json('ok') !== true) {
                return $this->rejected($appName);
            }

            $account = collect([$response->json('user'), $response->json('team')])->filter()->implode(' @ ');

            return $this->fromResponse($response, $appName, $account !== '' ? $account : null);
        }

        if ($connectorKey === 'gmail' || str_starts_with($connectorKey, 'google_')) {
            $response = Http::acceptJson()->timeout(self::TIMEOUT_SECONDS)
                ->get('https://www.googleapis.com/oauth2/v3/tokeninfo', ['access_token' => $token]);

            if ($response->status() === 400) {
                return $this->rejected($appName);
            }

            return $this->fromResponse($response, $appName, $response->json('email'));
        }

        if ($connectorKey === 'notion') {
            $response = $request->withHeaders(['Notion-Version' => '2022-06-28'])->get('https://api.notion.com/v1/users/me');
            $account = $response->json('bot.workspace_name') ?? $response->json('name');

            return $this->fromResponse($response, $appName, is_string($account) ? $account : null);
        }

        if ($connectorKey === 'linear') {
            $response = $request->post('https://api.linear.app/graphql', ['query' => '{ viewer { name email } }']);

            // Linear answers a rejected token with 200 and `errors` as often as with 401.
            if ($response->successful() && ! empty($response->json('errors'))) {
                return $this->rejected($appName);
            }

            return $this->fromResponse($response, $appName, $response->json('data.viewer.email') ?? $response->json('data.viewer.name'));
        }

        if ($connectorKey === 'jira') {
            $response = $request->get('https://api.atlassian.com/oauth/token/accessible-resources');
            $sites = collect((array) $response->json())->pluck('name')->filter()->implode(', ');

            if ($response->successful() && $sites === '') {
                return $this->result(false, 'This Atlassian account has no Jira site. Connect one that can open a site.');
            }

            return $this->fromResponse($response, $appName, $sites !== '' ? $sites : null);
        }

        if ($connectorKey === 'outlook') {
            $response = $request->get('https://graph.microsoft.com/v1.0/me');

            return $this->fromResponse($response, $appName, $response->json('mail') ?? $response->json('userPrincipalName'));
        }

        return $this->result(true, "{$appName} has a valid access token.");
    }

    /**
     * The check for an app connected with its own keys: ask the app who the
     * keys belong to (a mail server is only reached, and told who is logging in).
     *
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     *
     * @throws ConnectionException
     */
    private function checkManual(string $connectorKey, array $data, string $appName): array
    {
        $token = (string) ($data['api_key'] ?? '');

        if ($connectorKey === 'telegram') {
            $response = Http::acceptJson()->timeout(self::TIMEOUT_SECONDS)->get('https://api.telegram.org/bot'.$token.'/getMe');

            if ($response->status() === 401 || $response->status() === 404 || ($response->successful() && $response->json('ok') !== true)) {
                return $this->rejected($appName);
            }

            $username = $response->json('result.username');

            return $this->fromResponse($response, $appName, is_string($username) ? '@'.$username : null);
        }

        if ($connectorKey === 'discord') {
            $response = Http::withHeaders(['Authorization' => 'Bot '.$token])->acceptJson()->timeout(self::TIMEOUT_SECONDS)->get('https://discord.com/api/v10/users/@me');

            return $this->fromResponse($response, $appName, $response->json('username'));
        }

        if ($connectorKey === 'twilio') {
            $sid = (string) ($data['account_sid'] ?? '');

            if ($sid === '' || $token === '') {
                return $this->result(false, 'Twilio needs both the account SID and the auth token.');
            }

            $response = Http::withBasicAuth($sid, $token)->acceptJson()->timeout(self::TIMEOUT_SECONDS)->get("https://api.twilio.com/2010-04-01/Accounts/{$sid}.json");

            return $this->fromResponse($response, $appName, $response->json('friendly_name'));
        }

        if ($connectorKey === 'smtp') {
            return $this->checkSmtp($data, $appName);
        }

        return $this->checkMarketing($connectorKey, $data, $appName);
    }

    /**
     * The marketing apps: each is asked for its own account, the cheapest call
     * that needs a valid key.
     *
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     *
     * @throws ConnectionException
     */
    private function checkMarketing(string $connectorKey, array $data, string $appName): array
    {
        $key = trim((string) ($data['api_key'] ?? ''));
        $request = Http::acceptJson()->timeout(self::TIMEOUT_SECONDS);

        if ($key === '') {
            return $this->result(false, "{$appName} needs an API key.");
        }

        switch ($connectorKey) {
            case 'typeform':
                $host = strtolower((string) ($data['region'] ?? '')) === 'eu' ? 'api.eu.typeform.com' : 'api.typeform.com';
                $response = $request->withToken($key)->get("https://{$host}/me");

                return $this->fromResponse($response, $appName, $response->json('email') ?? $response->json('alias'));

            case 'mailchimp':
                $datacenter = str_contains($key, '-') ? substr(strrchr($key, '-'), 1) : '';

                if (preg_match('/^[a-z]{2,4}\d{1,3}$/', $datacenter) !== 1) {
                    return $this->result(false, 'A Mailchimp API key ends with its data center, like -us21. Copy the whole key.');
                }

                $response = $request->withBasicAuth('flows', $key)->get("https://{$datacenter}.api.mailchimp.com/3.0/");

                return $this->fromResponse($response, $appName, $response->json('account_name'));

            case 'kit':
                $response = $request->withHeaders(['X-Kit-Api-Key' => $key])->get('https://api.kit.com/v4/account');

                return $this->fromResponse($response, $appName, $response->json('account.name') ?? $response->json('user.email'));

            case 'activecampaign':
                try {
                    $root = AbstractActiveCampaignNode::apiRoot((string) ($data['api_url'] ?? ''));
                } catch (InvalidArgumentException $e) {
                    return $this->result(false, $e->getMessage());
                }

                $response = $request->withHeaders(['Api-Token' => $key])->get("{$root}/users/me");

                return $this->fromResponse($response, $appName, $response->json('user.email') ?? rtrim((string) $data['api_url'], '/'));

            case 'klaviyo':
                $response = $request->withHeaders(['Authorization' => 'Klaviyo-API-Key '.$key, 'revision' => '2024-10-15'])->get('https://a.klaviyo.com/api/accounts/');

                return $this->fromResponse($response, $appName, $response->json('data.0.attributes.contact_information.organization_name'));

            case 'brevo':
                $response = $request->withHeaders(['api-key' => $key])->get('https://api.brevo.com/v3/account');

                return $this->fromResponse($response, $appName, $response->json('email'));

            case 'trello':
                $appKey = trim((string) ($data['app_key'] ?? ''));

                if ($appKey === '') {
                    return $this->result(false, "{$appName} needs the API key as well as the token.");
                }

                $response = $request->get('https://api.trello.com/1/members/me', ['key' => $appKey, 'token' => $key, 'fields' => 'fullName,username']);

                return $this->fromResponse($response, $appName, $response->json('username') ?? $response->json('fullName'));

            case 'asana':
                $response = $request->withToken($key)->get('https://app.asana.com/api/1.0/users/me');

                return $this->fromResponse($response, $appName, $response->json('data.email') ?? $response->json('data.name'));

            case 'clickup':
                $response = $request->withHeaders(['Authorization' => $key])->get('https://api.clickup.com/api/v2/user');

                return $this->fromResponse($response, $appName, $response->json('user.email') ?? $response->json('user.username'));

            case 'monday':
                $response = $request->withHeaders(['Authorization' => $key, 'API-Version' => '2024-10'])->post('https://api.monday.com/v2', ['query' => '{ me { name email } }']);

                // monday answers a bad token with 200 and an errors list.
                if ($response->successful() && $response->json('errors') !== null) {
                    return $this->result(false, (string) ($response->json('errors.0.message') ?? 'monday.com refused the token.'));
                }

                return $this->fromResponse($response, $appName, $response->json('data.me.email'));

            case 'airtable':
                $response = $request->withToken($key)->get('https://api.airtable.com/v0/meta/whoami');

                return $this->fromResponse($response, $appName, $response->json('email') ?? $response->json('id'));

            case 'hubspot':
                $response = $request->withToken($key)->get('https://api.hubapi.com/account-info/v3/details');

                return $this->fromResponse($response, $appName, $response->json('portalId') !== null ? 'Portal '.$response->json('portalId') : null);

            case 'stripe':
                if (preg_match('/^(sk|rk)_(live|test)_/', $key) !== 1) {
                    return $this->result(false, 'A Stripe secret key starts with sk_ (or rk_ for a restricted key). A publishable key (pk_) can\'t be used.');
                }

                $response = $request->withToken($key)->get('https://api.stripe.com/v1/account');
                $name = $response->json('business_profile.name') ?? $response->json('settings.dashboard.display_name') ?? $response->json('email');

                return $this->fromResponse($response, $appName, ($name ?? $response->json('id')).(str_contains($key, '_test_') ? ' (test mode)' : ''));

            case 'shopify':
                $shop = AbstractShopifyNode::normalizeShop((string) ($data['shop'] ?? ''));

                if ($shop === null) {
                    return $this->result(false, 'Shopify needs the store address, like your-store.myshopify.com (not the store\'s own domain).');
                }

                $response = $request->withHeaders(['X-Shopify-Access-Token' => $key])->get("https://{$shop}/admin/api/".AbstractShopifyNode::API_VERSION.'/shop.json');

                return $this->fromResponse($response, $appName, $response->json('shop.name') ?? $shop);

            case 'pipedrive':
                $response = $request->withHeaders(['x-api-token' => $key])->get('https://api.pipedrive.com/v1/users/me');

                return $this->fromResponse($response, $appName, $response->json('data.email') ?? $response->json('data.name'));

            case 'salesforce':
                try {
                    $login = app(CrmAccessTokens::class)->salesforce($data);
                } catch (InvalidArgumentException|RuntimeException $e) {
                    return $this->result(false, $e->getMessage());
                }

                $response = $request->withToken($login['token'])->get($login['instance'].'/services/data/v62.0/limits');

                return $this->fromResponse($response, $appName, parse_url($login['instance'], PHP_URL_HOST) ?: null);

            case 'zoho':
                try {
                    $login = app(CrmAccessTokens::class)->zoho($data);
                } catch (InvalidArgumentException|RuntimeException $e) {
                    return $this->result(false, $e->getMessage());
                }

                $response = $request->withHeaders(['Authorization' => 'Zoho-oauthtoken '.$login['token']])->get($login['api'].'/crm/v7/users', ['type' => 'CurrentUser']);

                return $this->fromResponse($response, $appName, $response->json('users.0.email'));

            case 'zoho_desk':
            case 'zoho_books':
            case 'zoho_projects':
            case 'zoho_campaigns':
            case 'zoho_mail':
            case 'zoho_cliq':
                $zohoApp = substr($connectorKey, 5);

                try {
                    $login = app(CrmAccessTokens::class)->zohoApp($data, $zohoApp);
                } catch (InvalidArgumentException|RuntimeException $e) {
                    return $this->result(false, $e->getMessage());
                }

                [$path, $headers, $query] = match ($zohoApp) {
                    'desk' => ['/organizations', ['orgId' => trim((string) ($data['org_id'] ?? ''))], []],
                    'books' => ['/organizations', [], []],
                    'projects' => ['/portals', [], []],
                    'campaigns' => ['/getmailinglists', [], ['resfmt' => 'JSON', 'range' => 1, 'fromindex' => 1]],
                    'mail' => ['/accounts', [], []],
                    default => ['/users', [], ['limit' => 1]],
                };

                $response = $request->withHeaders(['Authorization' => 'Zoho-oauthtoken '.$login['token'], ...$headers])->get($login['base'].$path, $query);

                return $this->fromResponse($response, $appName, null);

            case 'resend':
                $response = $request->withToken($key)->get('https://api.resend.com/domains');

                // A key limited to sending can't list domains: it is refused for that, which still proves the key is real.
                return $response->status() === 401 && $response->json('name') === 'restricted_api_key'
                    ? $this->result(true, "Connected to {$appName} (a sending-only key).")
                    : $this->fromResponse($response, $appName, null);

            case 'postmark':
                $response = $request->withHeaders(['X-Postmark-Server-Token' => $key])->get('https://api.postmarkapp.com/server');

                return $this->fromResponse($response, $appName, $response->json('Name'));

            case 'mailgun':
                $domain = strtolower(trim((string) ($data['domain'] ?? '')));

                if (preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $domain) !== 1) {
                    return $this->result(false, 'Enter the sending domain, like mg.example.com.');
                }

                $host = strtolower((string) ($data['region'] ?? '')) === 'eu' ? 'api.eu.mailgun.net' : 'api.mailgun.net';
                $response = $request->withBasicAuth('api', $key)->get("https://{$host}/v4/domains/{$domain}");

                return $this->fromResponse($response, $appName, $response->json('domain.name'));

            default:
                $response = $request->withToken($key)->get('https://api.sendgrid.com/v3/user/account');

                // A key without the "user account" scope still works for what it was made for: only a refusal of the key itself is a failure.
                return $response->status() === 403 ? $this->result(true, "Connected to {$appName} (the key can't read the account name).") : $this->fromResponse($response, $appName, $response->json('type'));
        }
    }

    /**
     * Opens a connection to the mail server and logs in, without sending anything.
     *
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     */
    private function checkSmtp(array $data, string $appName): array
    {
        try {
            app(SmtpMailer::class)->verify($data);
        } catch (Throwable $e) {
            return $this->result(false, 'The mail server did not accept these settings: '.Str::limit($e->getMessage(), 200));
        }

        return $this->result(true, "Connected to {$appName}.", (string) ($data['from_address'] ?? '') ?: null);
    }

    /**
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     */
    private function fromResponse(Response $response, string $appName, ?string $account): array
    {
        if ($response->status() === 401 || $response->status() === 403) {
            return $this->rejected($appName);
        }

        if (! $response->successful()) {
            return $this->result(false, "{$appName} didn't respond as expected (HTTP {$response->status()}). Try again in a moment.");
        }

        return $this->result(true, "Connected to {$appName}.", $account);
    }

    /**
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     */
    private function rejected(string $appName): array
    {
        return $this->result(false, "{$appName} rejected this account's access. Reconnect it to keep it working.");
    }

    /**
     * @return array{ok: bool, message: string, account: ?string, tested_at: string}
     */
    private function result(bool $ok, string $message, ?string $account = null): array
    {
        return [
            'ok' => $ok,
            'message' => $message,
            'account' => $account,
            'tested_at' => now()->toIso8601String(),
        ];
    }
}
