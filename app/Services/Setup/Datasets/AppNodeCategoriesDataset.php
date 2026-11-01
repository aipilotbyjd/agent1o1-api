<?php

namespace App\Services\Setup\Datasets;

use App\Enums\Setup\Ownership;
use App\Models\Nodes\NodeCategory;
use App\Services\Setup\AbstractSetupDataset;

/**
 * One row per integration family (`kind: 'app'`); separate from
 * `NodeCategoriesDataset`'s core, built-in categories, since this file is
 * the one that grows with every new Stage 11 integration
 * (docs/WORKFLOWS_AGENTS_BUILD_PLAN.md), while the core list never changes.
 */
class AppNodeCategoriesDataset extends AbstractSetupDataset
{
    public function key(): string
    {
        return 'app-node-categories';
    }

    public function label(): string
    {
        return 'App node categories';
    }

    public function ownership(): Ownership
    {
        return Ownership::CodeOwned;
    }

    public function dependencies(): array
    {
        return ['node-categories'];
    }

    public function rows(): int
    {
        return NodeCategory::query()->where('kind', 'app')->count();
    }

    protected function dataFiles(): array
    {
        return [database_path('setup/app-node-categories.php')];
    }

    /**
     * What each integration is used for (the first function is its primary,
     * at most three) and who makes it. Adding an integration means adding a
     * line here; a test fails for one that is missing.
     *
     * @var array<string, array{0: ?string, 1: list<string>}>
     */
    private const TAXONOMY = [
        'slack' => [null, ['messaging']],
        'gmail' => ['google', ['email', 'productivity']],
        'github' => [null, ['developer']],
        'google_drive' => ['google', ['storage', 'productivity']],
        'google_sheets' => ['google', ['productivity']],
        'google_docs' => ['google', ['productivity']],
        'google_calendar' => ['google', ['productivity']],
        'google_tasks' => ['google', ['productivity']],
        'google_contacts' => ['google', ['productivity', 'crm-sales']],
        'google_forms' => ['google', ['forms', 'marketing']],
        'google_slides' => ['google', ['productivity']],
        'google_youtube' => ['google', ['media', 'marketing']],
        'google_analytics' => ['google', ['analytics', 'marketing']],
        'google_search_console' => ['google', ['analytics', 'marketing']],
        'outlook' => ['microsoft', ['email', 'productivity']],
        'notion' => [null, ['project-management', 'productivity']],
        'linear' => [null, ['project-management', 'developer']],
        'jira' => ['atlassian', ['project-management', 'developer']],
        'telegram' => [null, ['messaging']],
        'discord' => [null, ['messaging']],
        'twilio' => [null, ['messaging']],
        'smtp' => [null, ['email']],
        'typeform' => [null, ['forms', 'marketing']],
        'mailchimp' => [null, ['marketing', 'email']],
        'kit' => [null, ['marketing', 'email']],
        'activecampaign' => [null, ['marketing', 'crm-sales']],
        'klaviyo' => [null, ['marketing', 'email']],
        'brevo' => [null, ['email', 'marketing']],
        'sendgrid' => [null, ['email']],
        'resend' => [null, ['email']],
        'postmark' => [null, ['email']],
        'mailgun' => [null, ['email']],
        'trello' => ['atlassian', ['project-management']],
        'asana' => [null, ['project-management']],
        'clickup' => [null, ['project-management']],
        'monday' => [null, ['project-management']],
        'airtable' => [null, ['project-management', 'productivity']],
        'hubspot' => [null, ['crm-sales', 'marketing']],
        'pipedrive' => [null, ['crm-sales']],
        'salesforce' => [null, ['crm-sales']],
        'zoho' => ['zoho', ['crm-sales']],
        'zoho_desk' => ['zoho', ['support']],
        'zoho_books' => ['zoho', ['finance-payments']],
        'zoho_projects' => ['zoho', ['project-management']],
        'zoho_campaigns' => ['zoho', ['marketing', 'email']],
        'zoho_mail' => ['zoho', ['email']],
        'zoho_cliq' => ['zoho', ['messaging']],
        'stripe' => [null, ['finance-payments', 'commerce']],
        'shopify' => [null, ['commerce', 'finance-payments']],
        'brave_search' => [null, ['analytics', 'marketing']],
        'tavily' => [null, ['developer', 'analytics']],
        'hunter' => [null, ['crm-sales', 'marketing']],
        'apollo' => [null, ['crm-sales', 'marketing']],
        'maps_platform' => [null, ['developer', 'productivity']],
        'docusign' => [null, ['crm-sales', 'productivity']],
        'dropbox_sign' => [null, ['crm-sales', 'productivity']],
        'zoom' => [null, ['productivity', 'messaging']],
        'calendly' => [null, ['productivity', 'crm-sales']],
        'cal_com' => [null, ['productivity', 'crm-sales']],
        'microsoft_teams' => ['microsoft', ['productivity', 'messaging']],
    ];

    public function apply(): void
    {
        $categories = require database_path('setup/app-node-categories.php');

        foreach ($categories as $category) {
            [$vendor, $functions] = self::TAXONOMY[$category['slug']] ?? [null, ['other']];

            $values = [
                'name' => $category['name'],
                'description' => $category['description'],
                'icon' => $category['icon'],
                'color' => $category['color'],
                'sort_order' => $category['sort_order'],
                'kind' => 'app',
                'vendor' => $vendor,
                'functions' => $functions,
            ];

            // What an admin changed in the catalog stays as they left it.
            $row = NodeCategory::query()->firstOrNew(['slug' => $category['slug']]);
            $row->fill($row->exists ? $row->withoutAdminEdited($values) : $values)->save();
        }
    }

    /**
     * @return list<string>
     */
    public function definedSlugs(): array
    {
        return array_column(require database_path('setup/app-node-categories.php'), 'slug');
    }

    public function problems(): array
    {
        $problems = [];
        $slugs = $this->definedSlugs();
        $core = app(NodeCategoriesDataset::class)->definedSlugs();

        foreach ($this->duplicates($slugs) as $slug) {
            $problems[] = "App category [{$slug}] appears more than once.";
        }

        foreach (array_intersect($slugs, $core) as $slug) {
            $problems[] = "App category [{$slug}] uses the slug of a core category.";
        }

        foreach (require database_path('setup/app-node-categories.php') as $category) {
            foreach ($this->missing($category, ['slug', 'name', 'icon', 'color', 'description', 'sort_order']) as $field) {
                $problems[] = 'App category ['.($category['slug'] ?? '?')."] has no {$field}.";
            }
        }

        foreach (array_diff(array_keys(self::TAXONOMY), $slugs) as $slug) {
            $problems[] = "The taxonomy lists [{$slug}], which is not an app category.";
        }

        return $problems;
    }
}
