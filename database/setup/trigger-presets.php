<?php

use App\Enums\Triggers\TriggerType;

/**
 * The catalog shown in the trigger picker: the two most common schedules and
 * the priority-1 integrations from docs/NODES_CATALOG.md (GitHub, Slack,
 * Stripe). The rest grows alongside the connectors that back it.
 */
return [
    [
        'category' => 'schedule',
        'key' => 'schedule.daily',
        'name' => 'Daily at 9am',
        'type' => TriggerType::Schedule,
        'config' => ['cron' => '0 9 * * *'],
        'sort_order' => 1,
    ],

    [
        'category' => 'schedule',
        'key' => 'schedule.hourly',
        'name' => 'Every hour',
        'type' => TriggerType::Schedule,
        'config' => ['cron' => '0 * * * *'],
        'sort_order' => 2,
    ],

    [
        'category' => 'github',
        'key' => 'github.push',
        'name' => 'GitHub: On Push',
        'description' => 'Fires when a commit is pushed to a watched repository.',
        'type' => TriggerType::Webhook,
        'signature_scheme' => 'github',
        'dedupe_header' => 'X-GitHub-Delivery',
        'sort_order' => 1,
    ],

    [
        'category' => 'github',
        'key' => 'github.pull_request',
        'name' => 'GitHub: On Pull Request',
        'description' => 'Fires when a pull request is opened, updated, or closed.',
        'type' => TriggerType::Webhook,
        'signature_scheme' => 'github',
        'dedupe_header' => 'X-GitHub-Delivery',
        'sort_order' => 2,
    ],

    [
        'category' => 'slack',
        'key' => 'slack.event',
        'name' => 'Slack: Event',
        'description' => 'Fires on a subscribed Slack Events API event.',
        'type' => TriggerType::Webhook,
        'signature_scheme' => 'slack',
        'dedupe_payload_path' => 'event_id',
        'sort_order' => 1,
    ],

    [
        'category' => 'stripe',
        'key' => 'stripe.event',
        'name' => 'Stripe: Event',
        'description' => 'Fires on a subscribed Stripe webhook event.',
        'type' => TriggerType::Webhook,
        'signature_scheme' => 'stripe',
        'dedupe_payload_path' => 'id',
        'sort_order' => 1,
    ],
];
