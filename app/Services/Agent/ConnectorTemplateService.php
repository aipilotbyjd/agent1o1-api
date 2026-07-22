<?php

namespace App\Services\Agent;

/**
 * Connector templates on top of the generic API node (roadmap item 7).
 *
 * The engine already exposes flexible, generic app nodes (Slack, Sheets,
 * Stripe, Gmail, …). This service ships one-click presets over them: a curated
 * tool_name + tool_description + sensible operation so a user just plugs in a
 * credential instead of hand-writing a tool config. It keeps the underlying
 * genericity while giving the Gumloop-style "connectors" feel.
 *
 * Each template maps directly onto an AgentToolConfig row (node_type,
 * tool_name, tool_description).
 */
class ConnectorTemplateService
{
    /**
     * @return array<int, array{
     *   key: string, label: string, category: string, node_type: string,
     *   tool_name: string, tool_description: string, credential_type: ?string, icon: ?string
     * }>
     */
    public function all(): array
    {
        return [
            [
                'key' => 'slack_send_message',
                'label' => 'Slack — Send message',
                'category' => 'Communication',
                'node_type' => 'slack',
                'tool_name' => 'send_slack_message',
                'tool_description' => 'Post a message to a Slack channel or user. Provide the channel and text.',
                'credential_type' => 'slack',
                'icon' => 'slack',
            ],
            [
                'key' => 'gmail_send_email',
                'label' => 'Gmail — Send email',
                'category' => 'Communication',
                'node_type' => 'gmail',
                'tool_name' => 'send_gmail',
                'tool_description' => 'Send an email from the connected Gmail account. Provide to, subject, and body.',
                'credential_type' => 'google',
                'icon' => 'gmail',
            ],
            [
                'key' => 'google_sheets_append',
                'label' => 'Google Sheets — Append row',
                'category' => 'Data',
                'node_type' => 'google_sheets',
                'tool_name' => 'append_sheet_row',
                'tool_description' => 'Append a row of values to a Google Sheet. Provide the spreadsheet ID, range, and row values.',
                'credential_type' => 'google',
                'icon' => 'google_sheets',
            ],
            [
                'key' => 'stripe_create_customer',
                'label' => 'Stripe — Create customer',
                'category' => 'Payments',
                'node_type' => 'stripe',
                'tool_name' => 'create_stripe_customer',
                'tool_description' => 'Create a customer in Stripe. Provide the email and optional name/metadata.',
                'credential_type' => 'stripe',
                'icon' => 'stripe',
            ],
            [
                'key' => 'notion_create_page',
                'label' => 'Notion — Create page',
                'category' => 'Productivity',
                'node_type' => 'notion',
                'tool_name' => 'create_notion_page',
                'tool_description' => 'Create a page in a Notion database. Provide the parent database ID and page properties.',
                'credential_type' => 'notion',
                'icon' => 'notion',
            ],
            [
                'key' => 'airtable_create_record',
                'label' => 'Airtable — Create record',
                'category' => 'Data',
                'node_type' => 'airtable',
                'tool_name' => 'create_airtable_record',
                'tool_description' => 'Create a record in an Airtable base/table. Provide the base ID, table, and fields.',
                'credential_type' => 'airtable',
                'icon' => 'airtable',
            ],
            [
                'key' => 'discord_send_message',
                'label' => 'Discord — Send message',
                'category' => 'Communication',
                'node_type' => 'discord',
                'tool_name' => 'send_discord_message',
                'tool_description' => 'Send a message to a Discord channel via webhook or bot. Provide the channel and content.',
                'credential_type' => 'discord',
                'icon' => 'discord',
            ],
            [
                'key' => 'telegram_send_message',
                'label' => 'Telegram — Send message',
                'category' => 'Communication',
                'node_type' => 'telegram',
                'tool_name' => 'send_telegram_message',
                'tool_description' => 'Send a message via a Telegram bot. Provide the chat ID and text.',
                'credential_type' => 'telegram',
                'icon' => 'telegram',
            ],
            [
                'key' => 'http_request',
                'label' => 'HTTP — Any API request',
                'category' => 'Developer',
                'node_type' => 'http_request',
                'tool_name' => 'http_request',
                'tool_description' => 'Make an arbitrary HTTP request to any API. Provide method, url, headers, and body.',
                'credential_type' => null,
                'icon' => 'http',
            ],
        ];
    }

    /**
     * Look up a single template by its key.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        foreach ($this->all() as $template) {
            if ($template['key'] === $key) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Turn a template key into an AgentToolConfig-shaped array.
     *
     * @return array<string, mixed>|null
     */
    public function toToolConfig(string $key): ?array
    {
        $template = $this->find($key);

        if ($template === null) {
            return null;
        }

        return [
            'node_type' => $template['node_type'],
            'tool_name' => $template['tool_name'],
            'tool_description' => $template['tool_description'],
            'is_enabled' => true,
        ];
    }
}
