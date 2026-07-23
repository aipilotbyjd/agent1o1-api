<?php

namespace Database\Seeders;

use App\Enums\ExecutionMode;
use App\Enums\ExecutionNodeStatus;
use App\Enums\ExecutionStatus;
use App\Models\ExecutionLog;
use App\Models\ExecutionNode;
use App\Models\Run;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ArchiveTestExecutionsSeeder extends Seeder
{
    /**
     * Seed old executions for testing the archival system
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding test executions for archival testing...');

        // Get first workspace and workflow
        $workspace = Workspace::first();
        $workflow = Workflow::first();
        $user = User::first();

        if (! $workspace || ! $workflow || ! $user) {
            $this->command->error('Please ensure you have at least one workspace, workflow, and user in the database.');

            return;
        }

        $this->command->info("Using workspace: {$workspace->name}");
        $this->command->info("Using workflow: {$workflow->name}");

        // Skip if test executions already exist to prevent duplicates on re-run
        if (Run::where('workspace_id', $workspace->id)->exists()) {
            $this->command->warn('Test executions already exist for this workspace — skipping to prevent duplicates.');
            $this->command->line('  Run php artisan migrate:fresh --seed to start fresh.');

            return;
        }

        // Create executions at different ages for testing retention policies
        $executionAges = [
            5 => 10,   // 5 days old - 10 executions (should NOT be archived for Free plan - 3 day retention)
            10 => 15,  // 10 days old - 15 executions (should be archived for Starter plan - 7 day retention)
            35 => 20,  // 35 days old - 20 executions (should be archived for Pro plan - 30 day retention)
            100 => 15, // 100 days old - 15 executions (should be archived for Teams plan - 90 day retention)
        ];

        $totalCreated = 0;

        foreach ($executionAges as $daysOld => $count) {
            $this->command->info("Creating {$count} executions from {$daysOld} days ago...");

            for ($i = 0; $i < $count; $i++) {
                $execution = $this->createExecution($workspace, $workflow, $user, $daysOld);
                $this->createExecutionNodes($execution, rand(3, 8));
                $this->createExecutionLogs($execution, rand(10, 30));
                $totalCreated++;
            }
        }

        $this->command->info("✅ Created {$totalCreated} test executions!");
        $this->command->newLine();
        $this->command->info('Test the archival with:');
        $this->command->line('  php artisan executions:archive --dry-run');
        $this->command->line('  php artisan executions:archive');
    }

    /**
     * Create a test execution
     */
    private function createExecution(Workspace $workspace, Workflow $workflow, User $user, int $daysOld): Run
    {
        $createdAt = now()->subDays($daysOld)->subHours(rand(0, 23))->subMinutes(rand(0, 59));
        $startedAt = $createdAt->copy()->addSeconds(rand(1, 10));
        $finishedAt = $startedAt->copy()->addSeconds(rand(30, 300));

        return Run::create([
            'id' => Str::uuid(),
            'workspace_id' => $workspace->id,
            'workflow_id' => $workflow->id,
            'triggered_by' => $user->id,
            'status' => $this->randomStatus(),
            'mode' => $this->randomMode(),
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
            'credits_consumed' => rand(5, 50),
            'trigger_data' => [
                'source' => 'seeder',
                'test' => true,
            ],
            'result_data' => [
                'success' => true,
                'message' => 'Test execution completed',
            ],
            'created_at' => $createdAt,
            'updated_at' => $finishedAt,
        ]);
    }

    /**
     * Create execution nodes
     */
    private function createExecutionNodes(Run $execution, int $count): void
    {
        $nodeTypes = [
            'trigger.webhook',
            'http.request',
            'data.transform',
            'condition.if',
            'ai.llm',
            'apps.email',
        ];

        for ($i = 0; $i < $count; $i++) {
            $nodeType = $nodeTypes[array_rand($nodeTypes)];
            $startedAt = $execution->started_at->copy()->addSeconds($i * 10);
            $finishedAt = $startedAt->copy()->addSeconds(rand(5, 20));

            ExecutionNode::create([
                'id' => Str::uuid(),
                'execution_id' => $execution->id,
                'node_id' => 'node_'.Str::random(8),
                'node_run_key' => 'run_'.Str::random(12),
                'node_type' => $nodeType,
                'node_name' => ucfirst(str_replace('.', ' ', $nodeType)),
                'status' => ExecutionNodeStatus::Completed,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
                'input_data' => ['test' => true],
                'output_data' => ['result' => 'success'],
                'sequence' => $i + 1,
            ]);
        }
    }

    /**
     * Create execution logs
     */
    private function createExecutionLogs(Run $execution, int $count): void
    {
        $levels = ['debug', 'info', 'warning', 'error'];
        $messages = [
            'Node execution started',
            'Processing data',
            'API request sent',
            'Response received',
            'Data transformed',
            'Condition evaluated',
            'Workflow completed',
        ];

        $nodes = $execution->nodes;

        for ($i = 0; $i < $count; $i++) {
            $node = $nodes->random();

            ExecutionLog::create([
                'id' => Str::uuid(),
                'execution_id' => $execution->id,
                'workspace_id' => $execution->workspace_id,
                'node_id' => $node->node_id,
                'level' => $levels[array_rand($levels)],
                'message' => $messages[array_rand($messages)],
                'context' => [
                    'node_type' => $node->node_type,
                    'sequence' => $i + 1,
                ],
                'logged_at' => $execution->started_at->copy()->addSeconds($i * 5),
            ]);
        }
    }

    /**
     * Get random execution status
     */
    private function randomStatus(): ExecutionStatus
    {
        $statuses = [
            ExecutionStatus::Completed,
            ExecutionStatus::Completed,
            ExecutionStatus::Completed, // Bias towards completed
            ExecutionStatus::Failed,
            ExecutionStatus::Cancelled,
        ];

        return $statuses[array_rand($statuses)];
    }

    /**
     * Get random execution mode
     */
    private function randomMode(): ExecutionMode
    {
        $modes = [
            ExecutionMode::Webhook,
            ExecutionMode::Manual,
            ExecutionMode::Scheduled,
        ];

        return $modes[array_rand($modes)];
    }
}
