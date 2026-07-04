<?php

namespace App\Engine;

use App\Contracts\NodeHandler;
use App\Engine\Nodes\Apps;
use App\Engine\Nodes\Apps\GitLab\GitLabNode;
use App\Engine\Nodes\Apps\Telegram\TelegramNode;
use App\Engine\Nodes\Core;
use App\Engine\Nodes\Flow;

class NodeCatalog
{
    /** @var array<string,string> Map of type alias → canonical type */
    private const ALIASES = [
        // Trigger variants
        'trigger.webhook' => 'trigger',
        'trigger.schedule' => 'trigger',
        'trigger.manual' => 'trigger',
        'trigger.polling' => 'trigger',
        // Flow variants
        'flow.if' => 'condition',
        'flow.switch' => 'condition',
        'flow.loop' => 'loop',
        'flow.delay' => 'delay',
        'flow.wait' => 'wait',
        'flow.merge' => 'merge',
        'flow.try_catch' => 'try_catch',
        'flow.retry' => 'retry',
        // HTTP variants
        'http.request' => 'http_request',
        'http_request.get' => 'http_request',
        'http_request.post' => 'http_request',
        // AI variants
        'ai.llm' => 'agent',
        'ai.agent' => 'agent',
        // Data/transform variants
        'data.transform' => 'transform',
        'data.json_transform' => 'transform',
        'data.set_variable' => 'set_variable',
        'data.datetime' => 'data.date_time',
        'util.code' => 'code',
        'util.template' => 'transform',
        'util.logger' => 'data.debug.logger',
        'util.filter' => 'data.filter',
        // Communication variants
        'comm.send_email' => 'email',
    ];

    /** @var array<string,string> Map of canonical type → handler class */
    private const CORE_HANDLERS = [
        'trigger' => Core\TriggerNode::class,
        'http_request' => Core\HttpRequestNode::class,
        'transform' => Core\TransformNode::class,
        'code' => Core\CodeNode::class,
        'set_variable' => Core\SetVariableNode::class,
        'sub_workflow' => Core\SubWorkflowNode::class,
        'agent' => Core\AgentNode::class,
        'condition' => Flow\ConditionNode::class,
        'if' => Flow\ConditionNode::class,
        'switch' => Flow\ConditionNode::class,
        'loop' => Flow\LoopNode::class,
        'merge' => Flow\MergeNode::class,
        'delay' => Flow\DelayNode::class,
        'wait' => Flow\WaitNode::class,
        'try_catch' => Flow\TryCatchNode::class,
        'retry' => Flow\RetryNode::class,
    ];

    /** Map of app slug prefix → handler class (convention-based) */
    private const APP_HANDLERS = [
        Apps\Slack\SlackNode::TYPE => Apps\Slack\SlackNode::class,
        'github' => Apps\GitHub\GitHubNode::class,
        'discord' => Apps\Discord\DiscordNode::class,
        'stripe' => Apps\Stripe\StripeNode::class,
        'airtable' => Apps\Airtable\AirtableNode::class,
        'notion' => Apps\Notion\NotionNode::class,
        'hubspot' => Apps\Hubspot\HubspotNode::class,
        'linear' => Apps\Linear\LinearNode::class,
        'jira' => Apps\Jira\JiraNode::class,
        'trello' => Apps\Trello\TrelloNode::class,
        'salesforce' => Apps\Salesforce\SalesforceNode::class,
        'mailchimp' => Apps\Mailchimp\MailchimpNode::class,
        'twilio' => Apps\Twilio\TwilioNode::class,
        'sendgrid' => Apps\Sendgrid\SendgridNode::class,
        'twitter' => Apps\Twitter\TwitterNode::class,
        'twitch' => Apps\Twitch\TwitchNode::class,
        'dropbox' => Apps\Dropbox\DropboxNode::class,
        'aws_s3' => Apps\AwsS3\AwsS3Node::class,
        'ftp' => Apps\Ftp\FtpNode::class,
        'mysql' => Apps\Mysql\MysqlNode::class,
        'postgresql' => Apps\Postgresql\PostgresqlNode::class,
        'mongodb' => Apps\Mongodb\MongodbNode::class,
        'redis' => Apps\Redis\RedisNode::class,
        TelegramNode::TYPE => TelegramNode::class,
        GitLabNode::TYPE => GitLabNode::class,
        Apps\Google\GmailNode::TYPE => Apps\Google\GmailNode::class,
        Apps\Google\GoogleSheetsNode::TYPE => Apps\Google\GoogleSheetsNode::class,
        Apps\Google\GoogleDriveNode::TYPE => Apps\Google\GoogleDriveNode::class,
        Apps\Google\GoogleCalendarNode::TYPE => Apps\Google\GoogleCalendarNode::class,
        'openai' => Apps\OpenAi\OpenAiNode::class,
        'llm' => Apps\Ai\LlmNode::class,
        'mail' => Apps\Mail\MailNode::class,
        'email' => Apps\Communication\EmailNode::class,
        // Data util nodes
        'data.array' => Apps\Data\ArrayNode::class,
        'data.cache' => Apps\Data\CacheNode::class,
        'data.date_time' => Apps\Data\DateTimeNode::class,
        'data.filter' => Apps\Data\FilterNode::class,
        'data.json' => Apps\Data\JsonNode::class,
        'data.math' => Apps\Data\MathNode::class,
        'data.string' => Apps\Data\StringNode::class,
        'data.variable' => Apps\Data\VariableNode::class,
        'data.debug.logger' => Apps\Debug\LoggerNode::class,
        // RAG nodes
        'rag.chunker' => Apps\Rag\ChunkerNode::class,
        'rag.document_loader' => Apps\Rag\DocumentLoaderNode::class,
        'rag.query' => Apps\Rag\RagQueryNode::class,
        'rag.vector_store_writer' => Apps\Rag\VectorStoreWriterNode::class,
    ];

    private static array $cache = [];

    public static function resolve(string $type): ?string
    {
        if (isset(self::$cache[$type])) {
            return self::$cache[$type];
        }

        // 1. Alias resolution
        $canonical = self::ALIASES[$type] ?? $type;

        // 2. Core/flow handler
        if (isset(self::CORE_HANDLERS[$canonical])) {
            return self::$cache[$type] = self::CORE_HANDLERS[$canonical];
        }

        // 3. App handler map
        if (isset(self::APP_HANDLERS[$canonical])) {
            return self::$cache[$type] = self::APP_HANDLERS[$canonical];
        }

        // 4. Convention-based: "google_sheets.append_row" → GoogleSheetsNode
        $slug = explode('.', $canonical)[0];
        if (isset(self::APP_HANDLERS[$slug])) {
            return self::$cache[$type] = self::APP_HANDLERS[$slug];
        }

        return null;
    }

    public static function handler(string $type): ?NodeHandler
    {
        $class = self::resolve($type);

        if ($class === null || ! class_exists($class)) {
            return null;
        }

        return app($class);
    }

    public static function operation(string $type): ?string
    {
        $parts = explode('.', $type);

        return count($parts) > 1 ? $parts[1] : null;
    }

    public static function isAppNode(string $type): bool
    {
        $slug = explode('.', $type)[0];

        return isset(self::APP_HANDLERS[$slug]) || isset(self::APP_HANDLERS[$type]);
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
