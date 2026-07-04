<?php

namespace Database\Seeders;

use App\Models\TriggerType;
use App\Models\TriggerTypeField;
use Illuminate\Database\Seeder;

class TriggerTypeFieldSeeder extends Seeder
{
    public function run(): void
    {
        $fields = [
            // GitHub: On Push
            'github_push' => [
                [
                    'field_name' => 'owner',
                    'field_label' => 'Repository Owner',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'mycompany',
                    'help_text' => 'GitHub username or organization name',
                    'sort_order' => 1,
                ],
                [
                    'field_name' => 'repo',
                    'field_label' => 'Repository Name',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'backend',
                    'help_text' => 'Repository name (without owner)',
                    'sort_order' => 2,
                ],
                [
                    'field_name' => 'branch',
                    'field_label' => 'Branch (Optional)',
                    'field_type' => 'text',
                    'is_required' => false,
                    'placeholder' => 'main',
                    'help_text' => 'Leave empty to trigger on any branch',
                    'sort_order' => 3,
                ],
            ],

            // GitHub: On Pull Request
            'github_pull_request' => [
                [
                    'field_name' => 'owner',
                    'field_label' => 'Repository Owner',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'mycompany',
                    'sort_order' => 1,
                ],
                [
                    'field_name' => 'repo',
                    'field_label' => 'Repository Name',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'backend',
                    'sort_order' => 2,
                ],
                [
                    'field_name' => 'actions',
                    'field_label' => 'PR Actions',
                    'field_type' => 'multiselect',
                    'is_required' => false,
                    'help_text' => 'Select which actions trigger this (opened, synchronize, closed, etc.)',
                    'options' => [
                        ['label' => 'Opened', 'value' => 'opened'],
                        ['label' => 'Updated', 'value' => 'synchronize'],
                        ['label' => 'Closed', 'value' => 'closed'],
                        ['label' => 'Reopened', 'value' => 'reopened'],
                    ],
                    'sort_order' => 3,
                ],
            ],

            // Schedule: Daily
            'schedule_daily' => [
                [
                    'field_name' => 'time_of_day',
                    'field_label' => 'Time of Day',
                    'field_type' => 'time',
                    'is_required' => true,
                    'help_text' => 'What time should this run?',
                    'sort_order' => 1,
                ],
                [
                    'field_name' => 'timezone',
                    'field_label' => 'Timezone',
                    'field_type' => 'select',
                    'is_required' => true,
                    'options' => [
                        ['label' => 'America/New_York', 'value' => 'America/New_York'],
                        ['label' => 'America/Los_Angeles', 'value' => 'America/Los_Angeles'],
                        ['label' => 'Europe/London', 'value' => 'Europe/London'],
                        ['label' => 'Europe/Paris', 'value' => 'Europe/Paris'],
                        ['label' => 'Asia/Tokyo', 'value' => 'Asia/Tokyo'],
                        ['label' => 'UTC', 'value' => 'UTC'],
                    ],
                    'sort_order' => 2,
                ],
            ],

            // Schedule: Weekly
            'schedule_weekly' => [
                [
                    'field_name' => 'day_of_week',
                    'field_label' => 'Day of Week',
                    'field_type' => 'select',
                    'is_required' => true,
                    'options' => [
                        ['label' => 'Monday', 'value' => 'monday'],
                        ['label' => 'Tuesday', 'value' => 'tuesday'],
                        ['label' => 'Wednesday', 'value' => 'wednesday'],
                        ['label' => 'Thursday', 'value' => 'thursday'],
                        ['label' => 'Friday', 'value' => 'friday'],
                        ['label' => 'Saturday', 'value' => 'saturday'],
                        ['label' => 'Sunday', 'value' => 'sunday'],
                    ],
                    'sort_order' => 1,
                ],
                [
                    'field_name' => 'time_of_day',
                    'field_label' => 'Time of Day',
                    'field_type' => 'time',
                    'is_required' => true,
                    'sort_order' => 2,
                ],
                [
                    'field_name' => 'timezone',
                    'field_label' => 'Timezone',
                    'field_type' => 'select',
                    'is_required' => true,
                    'options' => [
                        ['label' => 'America/New_York', 'value' => 'America/New_York'],
                        ['label' => 'America/Los_Angeles', 'value' => 'America/Los_Angeles'],
                        ['label' => 'Europe/London', 'value' => 'Europe/London'],
                        ['label' => 'UTC', 'value' => 'UTC'],
                    ],
                    'sort_order' => 3,
                ],
            ],

            // Schedule: Cron
            'schedule_cron' => [
                [
                    'field_name' => 'cron_expression',
                    'field_label' => 'Cron Expression',
                    'field_type' => 'textarea',
                    'is_required' => true,
                    'placeholder' => '0 9 * * 1-5',
                    'help_text' => 'Standard cron syntax (minute hour day month weekday)',
                    'validation_regex' => '^((\d+,)+\d+|(\d+(\/|-)\d+)|\d+|\*) ((\d+,)+\d+|(\d+(\/|-)\d+)|\d+|\*) ((\d+,)+\d+|(\d+(\/|-)\d+)|\d+|\*) ((\d+,)+\d+|(\d+(\/|-)\d+)|\d+|\*) ((\d+,)+\d+|(\d+(\/|-)\d+)|\d+|\*)$',
                    'sort_order' => 1,
                ],
                [
                    'field_name' => 'timezone',
                    'field_label' => 'Timezone',
                    'field_type' => 'select',
                    'is_required' => true,
                    'options' => [
                        ['label' => 'UTC', 'value' => 'UTC'],
                        ['label' => 'America/New_York', 'value' => 'America/New_York'],
                        ['label' => 'Europe/London', 'value' => 'Europe/London'],
                    ],
                    'sort_order' => 2,
                ],
            ],

            // Webhook: Custom
            'webhook_custom' => [
                [
                    'field_name' => 'path',
                    'field_label' => 'Webhook Path',
                    'field_type' => 'text',
                    'is_required' => false,
                    'placeholder' => 'my-webhook',
                    'help_text' => 'Custom path for your webhook URL (auto-generated if empty)',
                    'sort_order' => 1,
                ],
                [
                    'field_name' => 'auth_type',
                    'field_label' => 'Authentication',
                    'field_type' => 'select',
                    'is_required' => false,
                    'options' => [
                        ['label' => 'None', 'value' => 'none'],
                        ['label' => 'Basic Auth', 'value' => 'basic'],
                        ['label' => 'Bearer Token', 'value' => 'bearer'],
                        ['label' => 'API Key Header', 'value' => 'api_key'],
                    ],
                    'sort_order' => 2,
                ],
            ],

            // API Polling
            'polling_api' => [
                [
                    'field_name' => 'endpoint_url',
                    'field_label' => 'API Endpoint URL',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'https://api.example.com/items',
                    'help_text' => 'Full URL to the API endpoint',
                    'sort_order' => 1,
                ],
                [
                    'field_name' => 'interval_seconds',
                    'field_label' => 'Check Interval (seconds)',
                    'field_type' => 'number',
                    'is_required' => true,
                    'placeholder' => '300',
                    'help_text' => 'How often to check (minimum 60 seconds)',
                    'sort_order' => 2,
                ],
                [
                    'field_name' => 'dedup_key_path',
                    'field_label' => 'Dedup Key Path (JSONPath)',
                    'field_type' => 'text',
                    'is_required' => false,
                    'placeholder' => 'id',
                    'help_text' => 'JSONPath to unique identifier (e.g., "data.0.id")',
                    'sort_order' => 3,
                ],
            ],

            // Slack: On Message
            'slack_message' => [
                [
                    'field_name' => 'channel',
                    'field_label' => 'Channel',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => '#general',
                    'help_text' => 'Channel name or ID',
                    'sort_order' => 1,
                ],
                [
                    'field_name' => 'include_bot_messages',
                    'field_label' => 'Include Bot Messages',
                    'field_type' => 'select',
                    'is_required' => false,
                    'options' => [
                        ['label' => 'Yes', 'value' => 'true'],
                        ['label' => 'No', 'value' => 'false'],
                    ],
                    'sort_order' => 2,
                ],
            ],

            // Airtable: On New Record
            'airtable_new_record' => [
                [
                    'field_name' => 'base_id',
                    'field_label' => 'Base ID',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'appXXXXXXXXXXXXXX',
                    'sort_order' => 1,
                ],
                [
                    'field_name' => 'table_id',
                    'field_label' => 'Table ID',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'tblXXXXXXXXXXXXXX',
                    'sort_order' => 2,
                ],
            ],

            // Gmail: On New Email
            'gmail_new_email' => [
                [
                    'field_name' => 'search_query',
                    'field_label' => 'Search Query',
                    'field_type' => 'text',
                    'is_required' => false,
                    'placeholder' => 'from:support@example.com',
                    'help_text' => 'Gmail search syntax (from:, to:, subject:, etc.)',
                    'sort_order' => 1,
                ],
            ],
        ];

        foreach ($fields as $triggerSlug => $fieldsList) {
            $triggerType = TriggerType::where('slug', $triggerSlug)->first();

            if (! $triggerType) {
                continue;
            }

            foreach ($fieldsList as $field) {
                TriggerTypeField::updateOrCreate(
                    ['trigger_type_id' => $triggerType->id, 'field_name' => $field['field_name']],
                    [
                        'field_label' => $field['field_label'],
                        'field_type' => $field['field_type'],
                        'is_required' => $field['is_required'] ?? false,
                        'is_secret' => $field['is_secret'] ?? false,
                        'placeholder' => $field['placeholder'] ?? null,
                        'help_text' => $field['help_text'] ?? null,
                        'validation_regex' => $field['validation_regex'] ?? null,
                        'options' => $field['options'] ?? null,
                        'sort_order' => $field['sort_order'] ?? 0,
                    ]
                );
            }
        }
    }
}
