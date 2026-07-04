<?php

namespace Database\Seeders;

use App\Models\TriggerCategory;
use Illuminate\Database\Seeder;

class TriggerCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug' => 'manual',
                'name' => 'Manual Trigger',
                'description' => 'Run workflow on demand when you click the Execute button',
                'icon' => 'play',
                'category_type' => 'manual',
                'is_active' => true,
            ],
            [
                'slug' => 'schedule',
                'name' => 'Scheduled Trigger',
                'description' => 'Run workflow at specific times or intervals',
                'icon' => 'clock',
                'category_type' => 'schedule',
                'is_active' => true,
            ],
            [
                'slug' => 'webhook',
                'name' => 'Custom Webhook',
                'description' => 'Run workflow when receiving HTTP requests',
                'icon' => 'webhook',
                'category_type' => 'webhook',
                'is_active' => true,
            ],
            [
                'slug' => 'polling',
                'name' => 'API Polling',
                'description' => 'Run workflow by periodically checking an API endpoint',
                'icon' => 'refresh',
                'category_type' => 'polling',
                'is_active' => true,
            ],
            // App-specific categories
            [
                'slug' => 'github',
                'name' => 'GitHub',
                'description' => 'Trigger on GitHub events (push, pull request, issue)',
                'icon' => 'github',
                'category_type' => 'app_specific',
                'is_active' => true,
            ],
            [
                'slug' => 'slack',
                'name' => 'Slack',
                'description' => 'Trigger on Slack events (message, mention, file upload)',
                'icon' => 'slack',
                'category_type' => 'app_specific',
                'is_active' => true,
            ],
            [
                'slug' => 'stripe',
                'name' => 'Stripe',
                'description' => 'Trigger on payment events (charge, invoice, customer)',
                'icon' => 'stripe',
                'category_type' => 'app_specific',
                'is_active' => true,
            ],
            [
                'slug' => 'google_sheets',
                'name' => 'Google Sheets',
                'description' => 'Trigger on new or updated rows in Google Sheets',
                'icon' => 'sheets',
                'category_type' => 'app_specific',
                'is_active' => true,
            ],
            [
                'slug' => 'airtable',
                'name' => 'Airtable',
                'description' => 'Trigger on new or updated records in Airtable',
                'icon' => 'airtable',
                'category_type' => 'app_specific',
                'is_active' => true,
            ],
            [
                'slug' => 'discord',
                'name' => 'Discord',
                'description' => 'Trigger on Discord events (message, reaction, member join)',
                'icon' => 'discord',
                'category_type' => 'app_specific',
                'is_active' => true,
            ],
            [
                'slug' => 'gmail',
                'name' => 'Gmail',
                'description' => 'Trigger on new Gmail messages matching criteria',
                'icon' => 'gmail',
                'category_type' => 'app_specific',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            TriggerCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
