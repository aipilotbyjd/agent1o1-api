<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            $this->command->warn('WorkflowSeeder: no users found — skipping.');

            return;
        }

        $workspace = Workspace::first();

        if (! $workspace) {
            $workspace = Workspace::create([
                'name' => 'Demo Workspace',
                'slug' => 'demo-workspace',
                'owner_id' => $user->id,
            ]);

            $workspace->members()->attach($user->id, [
                'id' => Str::uuid()->toString(),
                'role' => Role::Owner,
                'joined_at' => now(),
            ]);
        }

        if ($workspace->workflows()->exists()) {
            $this->command->info('WorkflowSeeder: workflows already exist — skipping.');

            return;
        }

        $demos = [
            [
                'name' => 'Lead Enrichment',
                'description' => 'Enrich new CRM leads with company data and AI-generated summaries.',
                'is_active' => true,
            ],
            [
                'name' => 'Support Ticket Triage',
                'description' => 'Classify incoming support tickets by priority and route to the right team.',
                'is_active' => true,
            ],
            [
                'name' => 'Daily Digest',
                'description' => 'Aggregate news and metrics each morning and send a Slack summary.',
                'is_active' => false,
            ],
            [
                'name' => 'Invoice Processor',
                'description' => 'Extract line items from uploaded invoices and sync to accounting.',
                'is_active' => false,
            ],
            [
                'name' => 'Social Monitor',
                'description' => 'Track brand mentions and alert on negative sentiment spikes.',
                'is_active' => false,
            ],
        ];

        foreach ($demos as $data) {
            Workflow::create([
                'workspace_id' => $workspace->id,
                'created_by' => $user->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'is_active' => $data['is_active'],
                'is_locked' => false,
            ]);
        }

        $this->command->info('WorkflowSeeder: created '.count($demos).' demo workflows.');
    }
}
