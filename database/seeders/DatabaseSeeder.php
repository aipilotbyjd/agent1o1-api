<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('password')],
        );

        $this->call([
            // Billing & credits
            PlanSeeder::class,
            CreditPackSeeder::class,

            // Credential types
            CredentialTypeSeeder::class,

            // Nodes & triggers
            NodeCategorySeeder::class,
            NodeSeeder::class,
            TriggerCategorySeeder::class,
            TriggerTypeSeeder::class,
            TriggerTypeFieldSeeder::class,

            // Templates
            WorkflowTemplateSeeder::class,
            AgentTemplateSeeder::class,
            TemplateCollectionSeeder::class,

            // Workspace bootstrap + demo data
            WorkflowSeeder::class,
            ProductManagerSkillSeeder::class,

            // Workspace billing, usage, and members
            CreditTransactionSeeder::class,
            SubscriptionSeeder::class,
            UsageDailySnapshotSeeder::class,
            WorkspaceMemberSeeder::class,
            WorkspaceUsagePeriodSeeder::class,

            // Demo/test data (requires existing workspace/workflow)
            OitWorkspaceSeeder::class,
            ArchiveTestExecutionsSeeder::class,
        ]);
    }
}
