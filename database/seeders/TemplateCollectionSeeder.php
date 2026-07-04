<?php

namespace Database\Seeders;

use App\Models\AgentTemplate;
use App\Models\TemplateCollection;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Seeder;

class TemplateCollectionSeeder extends Seeder
{
    public function run(): void
    {
        $wf = fn (string $slug) => optional(WorkflowTemplate::where('slug', $slug)->first())?->id;
        $ag = fn (string $slug) => optional(AgentTemplate::where('slug', $slug)->first())?->id;

        $collections = [
            [
                'name' => 'Customer Success Starter Kit',
                'slug' => 'customer-success-starter-kit',
                'description' => 'Everything you need to handle customer support, onboarding, and retention — ready to deploy in minutes.',
                'icon' => 'heart',
                'color' => '#3B82F6',
                'items' => array_filter([
                    $ag('customer-support-agent') ? ['type' => 'agent', 'template_id' => $ag('customer-support-agent'), 'note' => 'Handle day-to-day customer queries'] : null,
                    $ag('refund-handler') ? ['type' => 'agent', 'template_id' => $ag('refund-handler'), 'note' => 'Automate refund processing'] : null,
                    $wf('new-user-welcome-email') ? ['type' => 'workflow', 'template_id' => $wf('new-user-welcome-email'), 'note' => 'Welcome new users automatically'] : null,
                    $wf('lead-enrichment-pipeline') ? ['type' => 'workflow', 'template_id' => $wf('lead-enrichment-pipeline'), 'note' => 'Enrich leads before they reach sales'] : null,
                ]),
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'DevOps Starter Kit',
                'slug' => 'devops-starter-kit',
                'description' => 'A complete DevOps automation bundle: incident management, deployment workflows, and uptime monitoring.',
                'icon' => 'server',
                'color' => '#DC2626',
                'items' => array_filter([
                    $ag('incident-commander') ? ['type' => 'agent', 'template_id' => $ag('incident-commander'), 'note' => 'Automate incident response coordination'] : null,
                    $ag('deploy-assistant') ? ['type' => 'agent', 'template_id' => $ag('deploy-assistant'), 'note' => 'Safe, guided deployments'] : null,
                    $wf('cicd-deployment-notifier') ? ['type' => 'workflow', 'template_id' => $wf('cicd-deployment-notifier'), 'note' => 'Broadcast deploy status to the team'] : null,
                    $wf('uptime-monitor-alert') ? ['type' => 'workflow', 'template_id' => $wf('uptime-monitor-alert'), 'note' => 'Get alerted instantly on downtime'] : null,
                    $wf('multi-channel-error-alert') ? ['type' => 'workflow', 'template_id' => $wf('multi-channel-error-alert'), 'note' => 'Notify via Slack and email on errors'] : null,
                    $wf('database-backup-reminder') ? ['type' => 'workflow', 'template_id' => $wf('database-backup-reminder'), 'note' => 'Never miss a backup check'] : null,
                ]),
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Content Machine',
                'slug' => 'content-machine',
                'description' => 'Automate your content pipeline from idea to publish — blog posts, social media, and weekly digests.',
                'icon' => 'newspaper',
                'color' => '#7C3AED',
                'items' => array_filter([
                    $ag('blog-writer') ? ['type' => 'agent', 'template_id' => $ag('blog-writer'), 'note' => 'Draft SEO blog posts on demand'] : null,
                    $ag('social-media-manager') ? ['type' => 'agent', 'template_id' => $ag('social-media-manager'), 'note' => 'Repurpose content across platforms'] : null,
                    $wf('weekly-digest-email') ? ['type' => 'workflow', 'template_id' => $wf('weekly-digest-email'), 'note' => 'Automated weekly newsletter'] : null,
                    $wf('daily-report-via-email') ? ['type' => 'workflow', 'template_id' => $wf('daily-report-via-email'), 'note' => 'Daily analytics summary'] : null,
                ]),
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Sales Acceleration Bundle',
                'slug' => 'sales-acceleration-bundle',
                'description' => 'Qualify leads faster, book demos automatically, and sync payments to your CRM.',
                'icon' => 'chart-bar',
                'color' => '#10B981',
                'items' => array_filter([
                    $ag('lead-qualifier') ? ['type' => 'agent', 'template_id' => $ag('lead-qualifier'), 'note' => 'Qualify inbound leads automatically'] : null,
                    $ag('demo-scheduler') ? ['type' => 'agent', 'template_id' => $ag('demo-scheduler'), 'note' => 'Book demo calls without back-and-forth'] : null,
                    $wf('lead-enrichment-pipeline') ? ['type' => 'workflow', 'template_id' => $wf('lead-enrichment-pipeline'), 'note' => 'Enrich leads with company data'] : null,
                    $wf('stripe-payment-to-crm') ? ['type' => 'workflow', 'template_id' => $wf('stripe-payment-to-crm'), 'note' => 'Auto-update CRM on payment'] : null,
                ]),
                'is_featured' => false,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Data & Analytics Stack',
                'slug' => 'data-analytics-stack',
                'description' => 'Build a self-service analytics layer: query databases in plain English, import CSVs, and sync data automatically.',
                'icon' => 'chart-pie',
                'color' => '#F59E0B',
                'items' => array_filter([
                    $ag('sql-assistant') ? ['type' => 'agent', 'template_id' => $ag('sql-assistant'), 'note' => 'Natural language database queries'] : null,
                    $wf('scheduled-data-sync') ? ['type' => 'workflow', 'template_id' => $wf('scheduled-data-sync'), 'note' => 'Hourly data sync from external APIs'] : null,
                    $wf('csv-to-database-import') ? ['type' => 'workflow', 'template_id' => $wf('csv-to-database-import'), 'note' => 'Bulk CSV import pipeline'] : null,
                    $wf('form-submission-to-google-sheets') ? ['type' => 'workflow', 'template_id' => $wf('form-submission-to-google-sheets'), 'note' => 'Capture form data to spreadsheet'] : null,
                ]),
                'is_featured' => false,
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($collections as $collection) {
            $collection['items'] = array_values($collection['items']);
            TemplateCollection::updateOrCreate(
                ['slug' => $collection['slug']],
                $collection
            );
        }
    }
}
