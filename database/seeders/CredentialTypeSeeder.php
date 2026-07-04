<?php

namespace Database\Seeders;

use App\Models\CredentialType;
use Illuminate\Database\Seeder;

class CredentialTypeSeeder extends Seeder
{
    /**
     * Credential types to disable. Add a key string here to seed it as inactive.
     *
     * @var list<string>
     */
    private const DISABLED_TYPES = [
        // 'google_oauth2',
        // 'openai',
    ];

    /** @return array{authorization_url:string,token_url:string,client_id:string,client_secret:string,scopes:list<string>,use_pkce:bool,provider:string} */
    private function oauth(string $provider, string $authUrl, string $tokenUrl, array $scopes, bool $pkce = false): array
    {
        return [
            'provider' => $provider,
            'authorize_url' => $authUrl,
            'token_url' => $tokenUrl,
            'client_id' => config("services.oauth.{$provider}.client_id", ''),
            'client_secret' => config("services.oauth.{$provider}.client_secret", ''),
            'scopes' => $scopes,
            'use_pkce' => $pkce,
        ];
    }

    /** @return array{required:list<string>,properties:array<string,mixed>} */
    private function schema(array $required, array $properties): array
    {
        return ['required' => $required, 'properties' => $properties];
    }

    private function field(string $label, string $type = 'string', bool $secret = false, ?string $placeholder = null): array
    {
        $f = ['type' => $type, 'label' => $label, 'secret' => $secret];
        if ($placeholder) {
            $f['placeholder'] = $placeholder;
        }

        return $f;
    }

    private function oauthTokenSchema(): array
    {
        return $this->schema([], [
            'access_token' => $this->field('Access Token', 'string', true),
            'refresh_token' => $this->field('Refresh Token', 'string', true),
            'token_type' => $this->field('Token Type', 'string', false),
            'expires_in' => $this->field('Expires In (seconds)', 'integer', false),
            'scope' => $this->field('Granted Scopes', 'string', false),
        ]);
    }

    public function run(): void
    {
        $types = [

            // ── AI ────────────────────────────────────────────────────────
            [
                'key' => 'openai', 'name' => 'OpenAI', 'color' => '#10a37f', 'icon' => 'sparkles',
                'auth_type' => 'api_key',
                'description' => 'Connect to OpenAI APIs — GPT, DALL-E, Whisper, Embeddings.',
                'docs_url' => 'https://platform.openai.com/api-keys',
                'fields' => $this->schema(['api_key'], [
                    'api_key' => $this->field('API Key', 'string', true, 'sk-proj-...'),
                    'organization' => $this->field('Organization ID (optional)', 'string', false, 'org-...'),
                    'base_url' => $this->field('Base URL (optional override)', 'string', false, 'https://api.openai.com/v1'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'anthropic', 'name' => 'Anthropic', 'color' => '#c96442', 'icon' => 'cpu-chip',
                'auth_type' => 'api_key',
                'description' => 'Connect to Anthropic Claude models.',
                'docs_url' => 'https://console.anthropic.com/account/keys',
                'fields' => $this->schema(['api_key'], [
                    'api_key' => $this->field('API Key', 'string', true, 'sk-ant-...'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'google_ai', 'name' => 'Google AI (Gemini)', 'color' => '#4285f4', 'icon' => 'sparkles',
                'auth_type' => 'api_key',
                'description' => 'Connect to Google Gemini and related AI models via API key.',
                'docs_url' => 'https://aistudio.google.com/app/apikey',
                'fields' => $this->schema(['api_key'], [
                    'api_key' => $this->field('API Key', 'string', true),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'azure_openai', 'name' => 'Azure OpenAI', 'color' => '#0078d4', 'icon' => 'cpu-chip',
                'auth_type' => 'api_key',
                'description' => 'Connect to OpenAI models hosted on Microsoft Azure.',
                'docs_url' => 'https://learn.microsoft.com/en-us/azure/cognitive-services/openai/',
                'fields' => $this->schema(['resource_name', 'deployment_name', 'api_key', 'api_version'], [
                    'resource_name' => $this->field('Resource Name', 'string', false, 'my-azure-openai'),
                    'deployment_name' => $this->field('Deployment Name', 'string', false, 'gpt-4o'),
                    'api_key' => $this->field('API Key', 'string', true),
                    'api_version' => $this->field('API Version', 'string', false, '2024-02-01'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'groq', 'name' => 'Groq', 'color' => '#f55036', 'icon' => 'bolt',
                'auth_type' => 'api_key',
                'description' => 'Connect to Groq for ultra-fast LLM inference.',
                'docs_url' => 'https://console.groq.com/keys',
                'fields' => $this->schema(['api_key'], [
                    'api_key' => $this->field('API Key', 'string', true, 'gsk_...'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'hugging_face', 'name' => 'Hugging Face', 'color' => '#ff9d00', 'icon' => 'sparkles',
                'auth_type' => 'api_key',
                'description' => 'Connect to Hugging Face Inference API and Hub.',
                'docs_url' => 'https://huggingface.co/settings/tokens',
                'fields' => $this->schema(['api_key'], [
                    'api_key' => $this->field('API Token', 'string', true, 'hf_...'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'mistral', 'name' => 'Mistral AI', 'color' => '#ff7000', 'icon' => 'sparkles',
                'auth_type' => 'api_key',
                'description' => 'Connect to Mistral AI models.',
                'docs_url' => 'https://console.mistral.ai/api-keys/',
                'fields' => $this->schema(['api_key'], [
                    'api_key' => $this->field('API Key', 'string', true),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'cohere', 'name' => 'Cohere', 'color' => '#39594d', 'icon' => 'sparkles',
                'auth_type' => 'api_key',
                'description' => 'Connect to Cohere AI for text generation and embeddings.',
                'docs_url' => 'https://dashboard.cohere.com/api-keys',
                'fields' => $this->schema(['api_key'], [
                    'api_key' => $this->field('API Key', 'string', true),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'perplexity', 'name' => 'Perplexity AI', 'color' => '#20808d', 'icon' => 'magnifying-glass',
                'auth_type' => 'api_key',
                'description' => 'Connect to Perplexity for AI-powered search and reasoning.',
                'docs_url' => 'https://www.perplexity.ai/settings/api',
                'fields' => $this->schema(['api_key'], [
                    'api_key' => $this->field('API Key', 'string', true, 'pplx-...'),
                ]),
                'oauth' => null,
            ],

            // ── Communication ─────────────────────────────────────────────
            [
                'key' => 'slack', 'name' => 'Slack', 'color' => '#4a154b', 'icon' => 'chat-bubble-left',
                'auth_type' => 'api_key',
                'description' => 'Connect via a Slack Bot Token to post messages and interact with channels.',
                'docs_url' => 'https://api.slack.com/authentication/token-types',
                'fields' => $this->schema(['bot_token'], [
                    'bot_token' => $this->field('Bot Token', 'string', true, 'xoxb-...'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'discord', 'name' => 'Discord', 'color' => '#5865f2', 'icon' => 'chat-bubble-bottom-center-text',
                'auth_type' => 'api_key',
                'description' => 'Connect to Discord using a Bot Token to send messages.',
                'docs_url' => 'https://discord.com/developers/docs/getting-started',
                'fields' => $this->schema(['bot_token'], [
                    'bot_token' => $this->field('Bot Token', 'string', true),
                    'webhook_url' => $this->field('Webhook URL (optional)', 'string', false),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'telegram', 'name' => 'Telegram', 'color' => '#2ca5e0', 'icon' => 'paper-airplane',
                'auth_type' => 'api_key',
                'description' => 'Connect to the Telegram Bot API to send messages and notifications.',
                'docs_url' => 'https://core.telegram.org/bots#botfather',
                'fields' => $this->schema(['bot_token'], [
                    'bot_token' => $this->field('Bot Token', 'string', true),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'microsoft_teams', 'name' => 'Microsoft Teams', 'color' => '#6264a7', 'icon' => 'chat-bubble-left',
                'auth_type' => 'api_key',
                'description' => 'Post messages to Microsoft Teams channels via Incoming Webhook.',
                'docs_url' => 'https://learn.microsoft.com/en-us/microsoftteams/platform/webhooks-and-connectors/how-to/add-incoming-webhook',
                'fields' => $this->schema(['webhook_url'], [
                    'webhook_url' => $this->field('Incoming Webhook URL', 'string', true, 'https://...webhook.office.com/...'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'smtp', 'name' => 'SMTP', 'color' => '#ea4335', 'icon' => 'envelope',
                'auth_type' => 'api_key',
                'description' => 'Send emails via any SMTP server (Gmail, Outlook, Mailgun, etc.).',
                'docs_url' => 'https://nodemailer.com/smtp/',
                'fields' => $this->schema(['host', 'port', 'user', 'password'], [
                    'host' => $this->field('SMTP Host', 'string', false, 'smtp.gmail.com'),
                    'port' => $this->field('Port', 'integer', false, '587'),
                    'user' => $this->field('Username / Email', 'string', false),
                    'password' => $this->field('Password / App Password', 'string', true),
                    'from_email' => $this->field('From Email (optional)', 'string', false),
                    'from_name' => $this->field('From Name (optional)', 'string', false),
                    'ssl' => $this->field('Use TLS', 'boolean', false),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'sendgrid', 'name' => 'SendGrid', 'color' => '#1a82e2', 'icon' => 'envelope',
                'auth_type' => 'api_key',
                'description' => 'Send transactional and marketing emails via SendGrid.',
                'docs_url' => 'https://app.sendgrid.com/settings/api_keys',
                'fields' => $this->schema(['api_key'], [
                    'api_key' => $this->field('API Key', 'string', true, 'SG....'),
                    'from_email' => $this->field('Default From Email', 'string', false),
                    'from_name' => $this->field('Default From Name', 'string', false),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'mailgun', 'name' => 'Mailgun', 'color' => '#f06b36', 'icon' => 'envelope',
                'auth_type' => 'api_key',
                'description' => 'Send transactional emails via Mailgun.',
                'docs_url' => 'https://app.mailgun.com/settings/api_security',
                'fields' => $this->schema(['api_key', 'domain'], [
                    'api_key' => $this->field('API Key', 'string', true),
                    'domain' => $this->field('Sending Domain', 'string', false, 'mg.example.com'),
                    'region' => $this->field('Region (us or eu)', 'string', false, 'us'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'resend', 'name' => 'Resend', 'color' => '#000000', 'icon' => 'envelope',
                'auth_type' => 'api_key',
                'description' => 'Send emails via Resend email platform.',
                'docs_url' => 'https://resend.com/api-keys',
                'fields' => $this->schema(['api_key'], [
                    'api_key' => $this->field('API Key', 'string', true, 're_...'),
                    'from_email' => $this->field('Default From Email', 'string', false),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'twilio', 'name' => 'Twilio', 'color' => '#f22f46', 'icon' => 'phone',
                'auth_type' => 'api_key',
                'description' => 'Send SMS, make calls, and use other Twilio communication APIs.',
                'docs_url' => 'https://console.twilio.com/',
                'fields' => $this->schema(['account_sid', 'auth_token'], [
                    'account_sid' => $this->field('Account SID', 'string', false, 'AC...'),
                    'auth_token' => $this->field('Auth Token', 'string', true),
                    'from_number' => $this->field('Default From Number', 'string', false, '+1...'),
                ]),
                'oauth' => null,
            ],

            // ── Developer & DevOps ────────────────────────────────────────
            [
                'key' => 'github', 'name' => 'GitHub', 'color' => '#24292f', 'icon' => 'code-bracket',
                'auth_type' => 'api_key',
                'description' => 'Authenticate with GitHub using a Personal Access Token.',
                'docs_url' => 'https://github.com/settings/tokens',
                'fields' => $this->schema(['access_token'], [
                    'access_token' => $this->field('Personal Access Token', 'string', true, 'ghp_...'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'gitlab', 'name' => 'GitLab', 'color' => '#fc6d26', 'icon' => 'code-bracket',
                'auth_type' => 'api_key',
                'description' => 'Authenticate with GitLab using a Personal Access Token.',
                'docs_url' => 'https://docs.gitlab.com/ee/user/profile/personal_access_tokens.html',
                'fields' => $this->schema(['access_token'], [
                    'access_token' => $this->field('Personal Access Token', 'string', true),
                    'server_url' => $this->field('Server URL (blank = gitlab.com)', 'string', false, 'https://gitlab.com'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'jira', 'name' => 'Jira', 'color' => '#0052cc', 'icon' => 'clipboard-document-list',
                'auth_type' => 'api_key',
                'description' => 'Connect to Jira Cloud using email and an API token.',
                'docs_url' => 'https://support.atlassian.com/atlassian-account/docs/manage-api-tokens-for-your-atlassian-account/',
                'fields' => $this->schema(['host', 'email', 'api_token'], [
                    'host' => $this->field('Jira Host', 'string', false, 'https://yourorg.atlassian.net'),
                    'email' => $this->field('Account Email', 'string', false),
                    'api_token' => $this->field('API Token', 'string', true),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'linear', 'name' => 'Linear', 'color' => '#5e6ad2', 'icon' => 'clipboard-document-list',
                'auth_type' => 'api_key',
                'description' => 'Connect to Linear for issue tracking and project management.',
                'docs_url' => 'https://linear.app/settings/api',
                'fields' => $this->schema(['api_key'], [
                    'api_key' => $this->field('API Key', 'string', true, 'lin_api_...'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'pagerduty', 'name' => 'PagerDuty', 'color' => '#06ac38', 'icon' => 'bell-alert',
                'auth_type' => 'api_key',
                'description' => 'Create and manage incidents in PagerDuty.',
                'docs_url' => 'https://developer.pagerduty.com/docs/rest-api-v2/authentication/',
                'fields' => $this->schema(['api_key'], [
                    'api_key' => $this->field('API Key', 'string', true),
                    'from_email' => $this->field('From Email (required for some endpoints)', 'string', false),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'datadog', 'name' => 'Datadog', 'color' => '#632ca6', 'icon' => 'chart-bar',
                'auth_type' => 'api_key',
                'description' => 'Send metrics, events, and logs to Datadog.',
                'docs_url' => 'https://app.datadoghq.com/organization-settings/api-keys',
                'fields' => $this->schema(['api_key', 'app_key'], [
                    'api_key' => $this->field('API Key', 'string', true),
                    'app_key' => $this->field('Application Key', 'string', true),
                    'site' => $this->field('Site (e.g. datadoghq.com)', 'string', false, 'datadoghq.com'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'sentry', 'name' => 'Sentry', 'color' => '#362d59', 'icon' => 'bug-ant',
                'auth_type' => 'api_key',
                'description' => 'Connect to Sentry for error tracking and release management.',
                'docs_url' => 'https://sentry.io/settings/account/api/auth-tokens/',
                'fields' => $this->schema(['auth_token', 'organization_slug'], [
                    'auth_token' => $this->field('Auth Token', 'string', true),
                    'organization_slug' => $this->field('Organization Slug', 'string', false),
                    'host' => $this->field('Host (blank = sentry.io)', 'string', false, 'https://sentry.io'),
                ]),
                'oauth' => null,
            ],

            // ── Productivity ──────────────────────────────────────────────
            [
                'key' => 'notion', 'name' => 'Notion', 'color' => '#000000', 'icon' => 'document-text',
                'auth_type' => 'api_key',
                'description' => 'Connect to Notion using an internal integration token.',
                'docs_url' => 'https://developers.notion.com/docs/create-a-notion-integration',
                'fields' => $this->schema(['internal_integration_token'], [
                    'internal_integration_token' => $this->field('Internal Integration Token', 'string', true, 'secret_...'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'airtable', 'name' => 'Airtable', 'color' => '#18bfff', 'icon' => 'table-cells',
                'auth_type' => 'api_key',
                'description' => 'Connect to Airtable using a Personal Access Token.',
                'docs_url' => 'https://airtable.com/create/tokens',
                'fields' => $this->schema(['access_token'], [
                    'access_token' => $this->field('Personal Access Token', 'string', true, 'pat...'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'google_sheets', 'name' => 'Google Sheets', 'color' => '#34a853', 'icon' => 'table-cells',
                'auth_type' => 'api_key',
                'description' => 'Read and write Google Sheets data using a Service Account JSON.',
                'docs_url' => 'https://developers.google.com/sheets/api/guides/authorizing',
                'fields' => $this->schema(['service_account_json'], [
                    'service_account_json' => ['type' => 'string', 'label' => 'Service Account JSON', 'secret' => true, 'multiline' => true],
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'trello', 'name' => 'Trello', 'color' => '#0079bf', 'icon' => 'clipboard-document-list',
                'auth_type' => 'api_key',
                'description' => 'Connect to Trello to manage boards, lists, and cards.',
                'docs_url' => 'https://trello.com/app-key',
                'fields' => $this->schema(['api_key', 'api_token'], [
                    'api_key' => $this->field('API Key', 'string', false),
                    'api_token' => $this->field('API Token', 'string', true),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'asana', 'name' => 'Asana', 'color' => '#f06a6a', 'icon' => 'clipboard-document-list',
                'auth_type' => 'api_key',
                'description' => 'Connect to Asana using a Personal Access Token.',
                'docs_url' => 'https://app.asana.com/0/my-apps',
                'fields' => $this->schema(['access_token'], [
                    'access_token' => $this->field('Personal Access Token', 'string', true),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'clickup', 'name' => 'ClickUp', 'color' => '#7b68ee', 'icon' => 'check-circle',
                'auth_type' => 'api_key',
                'description' => 'Connect to ClickUp for task and project management.',
                'docs_url' => 'https://clickup.com/api/developer-portal/authentication/',
                'fields' => $this->schema(['api_key'], [
                    'api_key' => $this->field('API Key', 'string', true, 'pk_...'),
                ]),
                'oauth' => null,
            ],

            // ── CRM & Marketing ───────────────────────────────────────────
            [
                'key' => 'hubspot', 'name' => 'HubSpot', 'color' => '#ff7a59', 'icon' => 'user-group',
                'auth_type' => 'api_key',
                'description' => 'Connect to HubSpot CRM using a Private App access token.',
                'docs_url' => 'https://developers.hubspot.com/docs/api/private-apps',
                'fields' => $this->schema(['access_token'], [
                    'access_token' => $this->field('Private App Access Token', 'string', true, 'pat-na1-...'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'mailchimp', 'name' => 'Mailchimp', 'color' => '#ffe01b', 'icon' => 'envelope',
                'auth_type' => 'api_key',
                'description' => 'Connect to Mailchimp for email marketing and audience management.',
                'docs_url' => 'https://mailchimp.com/developer/marketing/guides/quick-start/',
                'fields' => $this->schema(['api_key'], [
                    'api_key' => $this->field('API Key', 'string', true, 'xxxx-us1'),
                    'server' => $this->field('Server Prefix (e.g. us1)', 'string', false, 'us1'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'brevo', 'name' => 'Brevo (Sendinblue)', 'color' => '#0092ff', 'icon' => 'envelope',
                'auth_type' => 'api_key',
                'description' => 'Connect to Brevo for email, SMS, and marketing campaigns.',
                'docs_url' => 'https://app.brevo.com/settings/keys/api',
                'fields' => $this->schema(['api_key'], [
                    'api_key' => $this->field('API Key', 'string', true),
                ]),
                'oauth' => null,
            ],

            // ── Databases ─────────────────────────────────────────────────
            [
                'key' => 'postgres', 'name' => 'PostgreSQL', 'color' => '#336791', 'icon' => 'circle-stack',
                'auth_type' => 'api_key',
                'description' => 'Connect to a PostgreSQL database.',
                'docs_url' => 'https://www.postgresql.org/docs/current/libpq-connect.html',
                'fields' => $this->schema(['host', 'port', 'database', 'user', 'password'], [
                    'host' => $this->field('Host', 'string', false, 'localhost'),
                    'port' => $this->field('Port', 'integer', false, '5432'),
                    'database' => $this->field('Database', 'string', false),
                    'user' => $this->field('Username', 'string', false),
                    'password' => $this->field('Password', 'string', true),
                    'ssl' => $this->field('Use SSL', 'boolean', false),
                    'ssl_cert' => $this->field('SSL Certificate (optional)', 'string', true),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'mysql', 'name' => 'MySQL', 'color' => '#4479a1', 'icon' => 'circle-stack',
                'auth_type' => 'api_key',
                'description' => 'Connect to a MySQL or MariaDB database.',
                'docs_url' => 'https://dev.mysql.com/doc/refman/8.0/en/connecting.html',
                'fields' => $this->schema(['host', 'port', 'database', 'user', 'password'], [
                    'host' => $this->field('Host', 'string', false, 'localhost'),
                    'port' => $this->field('Port', 'integer', false, '3306'),
                    'database' => $this->field('Database', 'string', false),
                    'user' => $this->field('Username', 'string', false),
                    'password' => $this->field('Password', 'string', true),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'mongodb', 'name' => 'MongoDB', 'color' => '#47a248', 'icon' => 'circle-stack',
                'auth_type' => 'api_key',
                'description' => 'Connect to MongoDB via connection string.',
                'docs_url' => 'https://www.mongodb.com/docs/drivers/node/current/fundamentals/connection/',
                'fields' => $this->schema(['connection_string'], [
                    'connection_string' => $this->field('Connection String', 'string', true, 'mongodb+srv://user:pass@cluster.mongodb.net/db'),
                    'database' => $this->field('Default Database', 'string', false),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'redis', 'name' => 'Redis', 'color' => '#d82c20', 'icon' => 'server',
                'auth_type' => 'api_key',
                'description' => 'Connect to a Redis server.',
                'docs_url' => 'https://redis.io/docs/connect/',
                'fields' => $this->schema(['host', 'port'], [
                    'host' => $this->field('Host', 'string', false, 'localhost'),
                    'port' => $this->field('Port', 'integer', false, '6379'),
                    'password' => $this->field('Password (optional)', 'string', true),
                    'db' => $this->field('Database Index', 'integer', false, '0'),
                    'tls' => $this->field('Use TLS', 'boolean', false),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'elasticsearch', 'name' => 'Elasticsearch', 'color' => '#00bfb3', 'icon' => 'magnifying-glass',
                'auth_type' => 'api_key',
                'description' => 'Connect to Elasticsearch for search and analytics.',
                'docs_url' => 'https://www.elastic.co/guide/en/elasticsearch/client/index.html',
                'fields' => $this->schema(['host'], [
                    'host' => $this->field('Host URL', 'string', false, 'https://localhost:9200'),
                    'username' => $this->field('Username (optional)', 'string', false),
                    'password' => $this->field('Password (optional)', 'string', true),
                    'api_key' => $this->field('API Key (optional)', 'string', true),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'supabase', 'name' => 'Supabase', 'color' => '#3fcf8e', 'icon' => 'circle-stack',
                'auth_type' => 'api_key',
                'description' => 'Connect to Supabase using the project URL and service role key.',
                'docs_url' => 'https://supabase.com/docs/guides/api',
                'fields' => $this->schema(['project_url', 'service_role_key'], [
                    'project_url' => $this->field('Project URL', 'string', false, 'https://xxxx.supabase.co'),
                    'service_role_key' => $this->field('Service Role Key', 'string', true, 'eyJ...'),
                    'anon_key' => $this->field('Anon Key (public, optional)', 'string', false),
                ]),
                'oauth' => null,
            ],

            // ── Cloud Storage ─────────────────────────────────────────────
            [
                'key' => 'aws_s3', 'name' => 'AWS S3', 'color' => '#ff9900', 'icon' => 'cloud',
                'auth_type' => 'api_key',
                'description' => 'Connect to AWS S3 for file storage.',
                'docs_url' => 'https://docs.aws.amazon.com/IAM/latest/UserGuide/id_credentials_access-keys.html',
                'fields' => $this->schema(['access_key_id', 'secret_access_key', 'region'], [
                    'access_key_id' => $this->field('Access Key ID', 'string', false, 'AKIA...'),
                    'secret_access_key' => $this->field('Secret Access Key', 'string', true),
                    'region' => $this->field('Region', 'string', false, 'us-east-1'),
                    'bucket' => $this->field('Default Bucket (optional)', 'string', false),
                    'endpoint' => $this->field('Custom Endpoint (S3-compatible, optional)', 'string', false),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'cloudinary', 'name' => 'Cloudinary', 'color' => '#3448c5', 'icon' => 'cloud',
                'auth_type' => 'api_key',
                'description' => 'Upload and manage media assets via Cloudinary.',
                'docs_url' => 'https://cloudinary.com/documentation/how_to_integrate_cloudinary',
                'fields' => $this->schema(['cloud_name', 'api_key', 'api_secret'], [
                    'cloud_name' => $this->field('Cloud Name', 'string', false),
                    'api_key' => $this->field('API Key', 'string', false),
                    'api_secret' => $this->field('API Secret', 'string', true),
                ]),
                'oauth' => null,
            ],

            // ── Payments ─────────────────────────────────────────────────
            [
                'key' => 'stripe', 'name' => 'Stripe', 'color' => '#635bff', 'icon' => 'credit-card',
                'auth_type' => 'api_key',
                'description' => 'Connect to Stripe for payments, subscriptions, and customer management.',
                'docs_url' => 'https://dashboard.stripe.com/apikeys',
                'fields' => $this->schema(['secret_key'], [
                    'secret_key' => $this->field('Secret Key', 'string', true, 'sk_live_... or sk_test_...'),
                    'webhook_secret' => $this->field('Webhook Signing Secret (optional)', 'string', true, 'whsec_...'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'razorpay', 'name' => 'Razorpay', 'color' => '#072654', 'icon' => 'credit-card',
                'auth_type' => 'api_key',
                'description' => 'Connect to Razorpay for payments (India).',
                'docs_url' => 'https://razorpay.com/docs/build/browser-integration/razorpay-checkout/standard/',
                'fields' => $this->schema(['key_id', 'key_secret'], [
                    'key_id' => $this->field('Key ID', 'string', false, 'rzp_live_...'),
                    'key_secret' => $this->field('Key Secret', 'string', true),
                ]),
                'oauth' => null,
            ],

            // ── Generic Auth ──────────────────────────────────────────────
            [
                'key' => 'http_header_auth', 'name' => 'HTTP Header Auth', 'color' => '#6b7280', 'icon' => 'key',
                'auth_type' => 'api_key',
                'description' => 'Authenticate HTTP requests using a custom header (e.g. Authorization: Bearer <token>).',
                'docs_url' => null,
                'fields' => $this->schema(['name', 'value'], [
                    'name' => $this->field('Header Name', 'string', false, 'Authorization'),
                    'value' => $this->field('Header Value', 'string', true, 'Bearer your-token'),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'http_basic_auth', 'name' => 'HTTP Basic Auth', 'color' => '#6b7280', 'icon' => 'lock-closed',
                'auth_type' => 'api_key',
                'description' => 'Authenticate HTTP requests using a username and password.',
                'docs_url' => null,
                'fields' => $this->schema(['user', 'password'], [
                    'user' => $this->field('Username', 'string', false),
                    'password' => $this->field('Password', 'string', true),
                ]),
                'oauth' => null,
            ],
            [
                'key' => 'http_query_auth', 'name' => 'HTTP Query Auth', 'color' => '#6b7280', 'icon' => 'key',
                'auth_type' => 'api_key',
                'description' => 'Authenticate HTTP requests by appending an API key as a query parameter.',
                'docs_url' => null,
                'fields' => $this->schema(['name', 'value'], [
                    'name' => $this->field('Query Param Name', 'string', false, 'api_key'),
                    'value' => $this->field('API Key Value', 'string', true),
                ]),
                'oauth' => null,
            ],

            // ── OAuth2 Types ──────────────────────────────────────────────
            [
                'key' => 'google_oauth2', 'name' => 'Google OAuth2', 'color' => '#4285f4', 'icon' => 'globe-alt',
                'auth_type' => 'oauth2',
                'description' => 'Connect to Google services (Sheets, Drive, Gmail, Calendar) via OAuth2.',
                'docs_url' => 'https://console.cloud.google.com/apis/credentials',
                'fields' => $this->oauthTokenSchema(),
                'oauth' => $this->oauth(
                    'google',
                    'https://accounts.google.com/o/oauth2/v2/auth',
                    'https://oauth2.googleapis.com/token',
                    [
                        'https://www.googleapis.com/auth/spreadsheets',
                        'https://www.googleapis.com/auth/drive',
                        'https://www.googleapis.com/auth/gmail.modify',
                        'https://www.googleapis.com/auth/gmail.send',
                        'https://www.googleapis.com/auth/calendar',
                        'openid',
                        'email',
                        'profile',
                    ],
                ) + ['extra_params' => ['access_type' => 'offline', 'prompt' => 'consent']],
            ],
            [
                'key' => 'github_oauth2', 'name' => 'GitHub OAuth2', 'color' => '#24292f', 'icon' => 'code-bracket',
                'auth_type' => 'oauth2',
                'description' => 'Connect to GitHub via OAuth2 (full repo and org access).',
                'docs_url' => 'https://docs.github.com/en/apps/oauth-apps/building-oauth-apps',
                'fields' => $this->oauthTokenSchema(),
                'oauth' => $this->oauth(
                    'github',
                    'https://github.com/login/oauth/authorize',
                    'https://github.com/login/oauth/access_token',
                    ['repo', 'read:user', 'read:org', 'workflow'],
                ),
            ],
            [
                'key' => 'slack_oauth2', 'name' => 'Slack OAuth2', 'color' => '#4a154b', 'icon' => 'chat-bubble-left',
                'auth_type' => 'oauth2',
                'description' => 'Connect to Slack via OAuth2 for user-level permissions.',
                'docs_url' => 'https://api.slack.com/authentication/oauth-v2',
                'fields' => $this->oauthTokenSchema(),
                'oauth' => $this->oauth(
                    'slack',
                    'https://slack.com/oauth/v2/authorize',
                    'https://slack.com/api/oauth.v2.access',
                    ['chat:write', 'chat:write.public', 'channels:read', 'users:read', 'files:write'],
                ),
            ],
            [
                'key' => 'notion_oauth2', 'name' => 'Notion OAuth2', 'color' => '#000000', 'icon' => 'document-text',
                'auth_type' => 'oauth2',
                'description' => 'Connect to Notion via OAuth2 for public integrations.',
                'docs_url' => 'https://developers.notion.com/docs/authorization',
                'fields' => $this->oauthTokenSchema(),
                'oauth' => $this->oauth(
                    'notion',
                    'https://api.notion.com/v1/oauth/authorize',
                    'https://api.notion.com/v1/oauth/token',
                    [],
                ),
            ],
            [
                'key' => 'microsoft_oauth2', 'name' => 'Microsoft / Azure AD OAuth2', 'color' => '#0078d4', 'icon' => 'globe-alt',
                'auth_type' => 'oauth2',
                'description' => 'Connect to Microsoft 365 (Outlook, Teams, OneDrive, Calendar) via OAuth2.',
                'docs_url' => 'https://learn.microsoft.com/en-us/azure/active-directory/develop/v2-oauth2-auth-code-flow',
                'fields' => $this->oauthTokenSchema(),
                'oauth' => $this->oauth(
                    'microsoft',
                    'https://login.microsoftonline.com/'.config('services.oauth.microsoft.tenant_id', 'common').'/oauth2/v2.0/authorize',
                    'https://login.microsoftonline.com/'.config('services.oauth.microsoft.tenant_id', 'common').'/oauth2/v2.0/token',
                    ['openid', 'offline_access', 'profile', 'email', 'Mail.Send', 'Calendars.ReadWrite', 'Files.ReadWrite'],
                ),
            ],
            [
                'key' => 'discord_oauth2', 'name' => 'Discord OAuth2', 'color' => '#5865f2', 'icon' => 'chat-bubble-bottom-center-text',
                'auth_type' => 'oauth2',
                'description' => 'Connect to Discord via OAuth2 for user and guild access.',
                'docs_url' => 'https://discord.com/developers/docs/topics/oauth2',
                'fields' => $this->oauthTokenSchema(),
                'oauth' => $this->oauth(
                    'discord',
                    'https://discord.com/api/oauth2/authorize',
                    'https://discord.com/api/oauth2/token',
                    ['identify', 'email', 'guilds', 'bot'],
                ),
            ],
            [
                'key' => 'linkedin_oauth2', 'name' => 'LinkedIn OAuth2', 'color' => '#0a66c2', 'icon' => 'user-group',
                'auth_type' => 'oauth2',
                'description' => 'Connect to LinkedIn to post, read profiles, and manage company pages.',
                'docs_url' => 'https://learn.microsoft.com/en-us/linkedin/shared/authentication/authorization-code-flow',
                'fields' => $this->oauthTokenSchema(),
                'oauth' => $this->oauth(
                    'linkedin',
                    'https://www.linkedin.com/oauth/v2/authorization',
                    'https://www.linkedin.com/oauth/v2/accessToken',
                    ['openid', 'profile', 'email', 'w_member_social'],
                ),
            ],
            [
                'key' => 'twitter_oauth2', 'name' => 'Twitter / X OAuth2', 'color' => '#000000', 'icon' => 'chat-bubble-left',
                'auth_type' => 'oauth2',
                'description' => 'Connect to Twitter/X API v2 via OAuth2 PKCE flow.',
                'docs_url' => 'https://developer.twitter.com/en/docs/authentication/oauth-2-0',
                'fields' => $this->oauthTokenSchema(),
                'oauth' => $this->oauth(
                    'twitter',
                    'https://twitter.com/i/oauth2/authorize',
                    'https://api.twitter.com/2/oauth2/token',
                    ['tweet.read', 'tweet.write', 'users.read', 'offline.access'],
                    true, // Twitter requires PKCE
                ),
            ],
            [
                'key' => 'dropbox_oauth2', 'name' => 'Dropbox OAuth2', 'color' => '#0061ff', 'icon' => 'cloud',
                'auth_type' => 'oauth2',
                'description' => 'Connect to Dropbox for file storage and sharing.',
                'docs_url' => 'https://developers.dropbox.com/oauth-guide',
                'fields' => $this->oauthTokenSchema(),
                'oauth' => $this->oauth(
                    'dropbox',
                    'https://www.dropbox.com/oauth2/authorize',
                    'https://api.dropboxapi.com/oauth2/token',
                    ['files.content.write', 'files.content.read', 'file_requests.write'],
                ) + ['extra_params' => ['token_access_type' => 'offline']],
            ],
            [
                'key' => 'hubspot_oauth2', 'name' => 'HubSpot OAuth2', 'color' => '#ff7a59', 'icon' => 'user-group',
                'auth_type' => 'oauth2',
                'description' => 'Connect to HubSpot CRM via OAuth2 for contacts, deals, and marketing.',
                'docs_url' => 'https://developers.hubspot.com/docs/api/oauth-quickstart-guide',
                'fields' => $this->oauthTokenSchema(),
                'oauth' => $this->oauth(
                    'hubspot',
                    'https://app.hubspot.com/oauth/authorize',
                    'https://api.hubapi.com/oauth/v1/token',
                    ['crm.objects.contacts.read', 'crm.objects.contacts.write', 'crm.objects.deals.read', 'crm.objects.deals.write'],
                ),
            ],
            [
                'key' => 'salesforce_oauth2', 'name' => 'Salesforce OAuth2', 'color' => '#00a1e0', 'icon' => 'user-group',
                'auth_type' => 'oauth2',
                'description' => 'Connect to Salesforce CRM via OAuth2.',
                'docs_url' => 'https://help.salesforce.com/s/articleView?id=sf.remoteaccess_authenticate.htm',
                'fields' => $this->oauthTokenSchema() + ['properties' => ['instance_url' => $this->field('Instance URL', 'string', false)]],
                'oauth' => $this->oauth(
                    'salesforce',
                    'https://login.salesforce.com/services/oauth2/authorize',
                    'https://login.salesforce.com/services/oauth2/token',
                    ['api', 'refresh_token', 'offline_access'],
                ),
            ],
            [
                'key' => 'airtable_oauth2', 'name' => 'Airtable OAuth2', 'color' => '#18bfff', 'icon' => 'table-cells',
                'auth_type' => 'oauth2',
                'description' => 'Connect to Airtable via OAuth2 (for public integrations and user-level access).',
                'docs_url' => 'https://airtable.com/developers/web/guides/oauth-integrations',
                'fields' => $this->oauthTokenSchema(),
                'oauth' => $this->oauth(
                    'airtable',
                    'https://airtable.com/oauth2/v1/authorize',
                    'https://airtable.com/oauth2/v1/token',
                    ['data.records:read', 'data.records:write', 'schema.bases:read'],
                    true, // PKCE required
                ),
            ],
            [
                'key' => 'zoom_oauth2', 'name' => 'Zoom OAuth2', 'color' => '#2d8cff', 'icon' => 'video-camera',
                'auth_type' => 'oauth2',
                'description' => 'Connect to Zoom to create meetings and manage webinars.',
                'docs_url' => 'https://developers.zoom.us/docs/integrations/oauth/',
                'fields' => $this->oauthTokenSchema(),
                'oauth' => $this->oauth(
                    'zoom',
                    'https://zoom.us/oauth/authorize',
                    'https://zoom.us/oauth/token',
                    ['meeting:write', 'webinar:write', 'user:read'],
                ),
            ],
        ];

        foreach ($types as $data) {
            $data['is_active'] = ! in_array($data['key'], self::DISABLED_TYPES);

            CredentialType::updateOrCreate(
                ['key' => $data['key']],
                $data,
            );
        }
    }
}
