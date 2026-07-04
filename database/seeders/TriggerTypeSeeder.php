<?php

namespace Database\Seeders;

use App\Models\TriggerCategory;
use App\Models\TriggerType;
use Illuminate\Database\Seeder;

class TriggerTypeSeeder extends Seeder
{
    public function run(): void
    {
        $triggerTypes = [
            // Manual triggers
            [
                'category_slug' => 'manual',
                'slug' => 'manual_trigger',
                'name' => 'Manual Trigger',
                'description' => 'Run the workflow when you click the Execute button',
                'execution_mode' => 'manual',
                'zapier_mode' => null,
                'requires_credential' => false,
                'requires_config_fields' => false,
            ],

            // Schedule triggers
            [
                'category_slug' => 'schedule',
                'slug' => 'schedule_hourly',
                'name' => 'Every Hour',
                'description' => 'Run workflow every hour',
                'execution_mode' => 'polling',
                'zapier_mode' => 'polling',
                'requires_credential' => false,
                'requires_config_fields' => true,
            ],
            [
                'category_slug' => 'schedule',
                'slug' => 'schedule_daily',
                'name' => 'Daily at Specific Time',
                'description' => 'Run workflow daily at a specific time',
                'execution_mode' => 'polling',
                'zapier_mode' => 'polling',
                'requires_credential' => false,
                'requires_config_fields' => true,
            ],
            [
                'category_slug' => 'schedule',
                'slug' => 'schedule_weekly',
                'name' => 'Weekly on Specific Day',
                'description' => 'Run workflow weekly on a specific day and time',
                'execution_mode' => 'polling',
                'zapier_mode' => 'polling',
                'requires_credential' => false,
                'requires_config_fields' => true,
            ],
            [
                'category_slug' => 'schedule',
                'slug' => 'schedule_monthly',
                'name' => 'Monthly on Specific Date',
                'description' => 'Run workflow monthly on a specific date and time',
                'execution_mode' => 'polling',
                'zapier_mode' => 'polling',
                'requires_credential' => false,
                'requires_config_fields' => true,
            ],
            [
                'category_slug' => 'schedule',
                'slug' => 'schedule_cron',
                'name' => 'Custom Cron Expression',
                'description' => 'Run workflow using a custom cron expression',
                'execution_mode' => 'polling',
                'zapier_mode' => 'polling',
                'requires_credential' => false,
                'requires_config_fields' => true,
            ],

            // Webhook triggers
            [
                'category_slug' => 'webhook',
                'slug' => 'webhook_custom',
                'name' => 'Custom Webhook',
                'description' => 'Trigger workflow when a POST request is sent to your webhook URL',
                'execution_mode' => 'webhook',
                'zapier_mode' => 'instant',
                'requires_credential' => false,
                'requires_config_fields' => true,
            ],

            // Polling triggers
            [
                'category_slug' => 'polling',
                'slug' => 'polling_api',
                'name' => 'API Polling',
                'description' => 'Trigger by periodically checking an API endpoint',
                'execution_mode' => 'polling',
                'zapier_mode' => 'polling',
                'requires_credential' => false,
                'requires_config_fields' => true,
            ],

            // GitHub triggers
            [
                'category_slug' => 'github',
                'slug' => 'github_push',
                'name' => 'On Push',
                'description' => 'Trigger when code is pushed to a repository',
                'execution_mode' => 'webhook',
                'zapier_mode' => 'instant',
                'requires_credential' => true,
                'requires_config_fields' => true,
                'webhook_events' => ['push'],
            ],
            [
                'category_slug' => 'github',
                'slug' => 'github_pull_request',
                'name' => 'On Pull Request',
                'description' => 'Trigger when a pull request is opened, updated, or closed',
                'execution_mode' => 'webhook',
                'zapier_mode' => 'instant',
                'requires_credential' => true,
                'requires_config_fields' => true,
                'webhook_events' => ['pull_request'],
            ],
            [
                'category_slug' => 'github',
                'slug' => 'github_issue',
                'name' => 'On Issue',
                'description' => 'Trigger when an issue is opened, updated, or closed',
                'execution_mode' => 'webhook',
                'zapier_mode' => 'instant',
                'requires_credential' => true,
                'requires_config_fields' => true,
                'webhook_events' => ['issues'],
            ],
            [
                'category_slug' => 'github',
                'slug' => 'github_release',
                'name' => 'On Release',
                'description' => 'Trigger when a new release is published',
                'execution_mode' => 'webhook',
                'zapier_mode' => 'instant',
                'requires_credential' => true,
                'requires_config_fields' => true,
                'webhook_events' => ['release'],
            ],

            // Slack triggers
            [
                'category_slug' => 'slack',
                'slug' => 'slack_message',
                'name' => 'On New Message',
                'description' => 'Trigger on new messages in a channel or conversation',
                'execution_mode' => 'webhook',
                'zapier_mode' => 'instant',
                'requires_credential' => true,
                'requires_config_fields' => true,
                'webhook_events' => ['message.channels', 'message.groups', 'message.im'],
            ],
            [
                'category_slug' => 'slack',
                'slug' => 'slack_mention',
                'name' => 'On App Mention',
                'description' => 'Trigger when the app is mentioned in a message',
                'execution_mode' => 'webhook',
                'zapier_mode' => 'instant',
                'requires_credential' => true,
                'requires_config_fields' => true,
                'webhook_events' => ['app_mention'],
            ],
            [
                'category_slug' => 'slack',
                'slug' => 'slack_reaction',
                'name' => 'On Reaction Added',
                'description' => 'Trigger when a reaction emoji is added to a message',
                'execution_mode' => 'webhook',
                'zapier_mode' => 'instant',
                'requires_credential' => true,
                'requires_config_fields' => true,
                'webhook_events' => ['reaction_added'],
            ],

            // Stripe triggers
            [
                'category_slug' => 'stripe',
                'slug' => 'stripe_charge_succeeded',
                'name' => 'On Charge Succeeded',
                'description' => 'Trigger when a charge succeeds',
                'execution_mode' => 'webhook',
                'zapier_mode' => 'instant',
                'requires_credential' => true,
                'requires_config_fields' => false,
                'webhook_events' => ['charge.succeeded'],
            ],
            [
                'category_slug' => 'stripe',
                'slug' => 'stripe_invoice_created',
                'name' => 'On Invoice Created',
                'description' => 'Trigger when an invoice is created',
                'execution_mode' => 'webhook',
                'zapier_mode' => 'instant',
                'requires_credential' => true,
                'requires_config_fields' => false,
                'webhook_events' => ['invoice.created'],
            ],
            [
                'category_slug' => 'stripe',
                'slug' => 'stripe_customer_created',
                'name' => 'On Customer Created',
                'description' => 'Trigger when a new customer is created',
                'execution_mode' => 'webhook',
                'zapier_mode' => 'instant',
                'requires_credential' => true,
                'requires_config_fields' => false,
                'webhook_events' => ['customer.created'],
            ],

            // Google Sheets triggers
            [
                'category_slug' => 'google_sheets',
                'slug' => 'sheets_new_row',
                'name' => 'On New Row',
                'description' => 'Trigger when a new row is added to a sheet',
                'execution_mode' => 'polling',
                'zapier_mode' => 'polling',
                'requires_credential' => true,
                'requires_config_fields' => true,
            ],
            [
                'category_slug' => 'google_sheets',
                'slug' => 'sheets_updated_row',
                'name' => 'On Row Updated',
                'description' => 'Trigger when an existing row is updated',
                'execution_mode' => 'polling',
                'zapier_mode' => 'polling',
                'requires_credential' => true,
                'requires_config_fields' => true,
            ],

            // Airtable triggers
            [
                'category_slug' => 'airtable',
                'slug' => 'airtable_new_record',
                'name' => 'On New Record',
                'description' => 'Trigger when a new record is created',
                'execution_mode' => 'webhook',
                'zapier_mode' => 'instant',
                'requires_credential' => true,
                'requires_config_fields' => true,
                'webhook_events' => ['records.created'],
            ],
            [
                'category_slug' => 'airtable',
                'slug' => 'airtable_updated_record',
                'name' => 'On Record Updated',
                'description' => 'Trigger when a record is updated',
                'execution_mode' => 'webhook',
                'zapier_mode' => 'instant',
                'requires_credential' => true,
                'requires_config_fields' => true,
                'webhook_events' => ['records.updated'],
            ],

            // Discord triggers
            [
                'category_slug' => 'discord',
                'slug' => 'discord_message',
                'name' => 'On Message',
                'description' => 'Trigger when a message is sent to a channel',
                'execution_mode' => 'webhook',
                'zapier_mode' => 'instant',
                'requires_credential' => true,
                'requires_config_fields' => true,
                'webhook_events' => ['message'],
            ],
            [
                'category_slug' => 'discord',
                'slug' => 'discord_reaction',
                'name' => 'On Reaction',
                'description' => 'Trigger when a reaction is added to a message',
                'execution_mode' => 'webhook',
                'zapier_mode' => 'instant',
                'requires_credential' => true,
                'requires_config_fields' => true,
                'webhook_events' => ['reaction'],
            ],

            // Gmail triggers
            [
                'category_slug' => 'gmail',
                'slug' => 'gmail_new_email',
                'name' => 'On New Email',
                'description' => 'Trigger when a new email matches your criteria',
                'execution_mode' => 'polling',
                'zapier_mode' => 'polling',
                'requires_credential' => true,
                'requires_config_fields' => true,
            ],
        ];

        foreach ($triggerTypes as $type) {
            $category = TriggerCategory::where('slug', $type['category_slug'])->first();

            if (! $category) {
                continue;
            }

            TriggerType::updateOrCreate(
                ['slug' => $type['slug']],
                [
                    'category_id' => $category->id,
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'execution_mode' => $type['execution_mode'],
                    'zapier_mode' => $type['zapier_mode'],
                    'requires_credential' => $type['requires_credential'],
                    'requires_config_fields' => $type['requires_config_fields'],
                    'webhook_events' => $type['webhook_events'] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }
}
