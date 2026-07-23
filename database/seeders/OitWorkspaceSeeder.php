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

class OitWorkspaceSeeder extends Seeder
{
    /**
     * Seed demo executions to OIT workspace
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding OIT workspace demo executions...');

        $workspaceId = '2c8e2a03-f7e1-4137-abcb-9cd1b74a8816';
        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            $this->command->error("Workspace with ID {$workspaceId} not found.");

            return;
        }

        $this->command->info("Using workspace: {$workspace->name}");

        $user = User::first();
        if (! $user) {
            $this->command->error('No users found. Please seed at least one user first.');

            return;
        }

        // Get or create workflow
        $workflow = $workspace->workflows()->first();
        if (! $workflow) {
            $workflow = Workflow::create([
                'workspace_id' => $workspace->id,
                'name' => 'OIT Demo Workflow',
                'description' => 'Demo workflow for OIT testing',
                'is_active' => true,
                'created_by' => $user->id,
            ]);
            $this->command->info("Created workflow: {$workflow->name}");
        } else {
            $this->command->info("Using existing workflow: {$workflow->name}");
        }

        // Skip if demo executions already exist for this workflow
        if ($workflow->executions()->exists()) {
            $this->command->info('Demo executions already exist — skipping to prevent duplicates.');

            return;
        }

        // Create demo executions
        $executionCount = 25;
        $this->command->info("Creating {$executionCount} demo executions...");

        for ($i = 0; $i < $executionCount; $i++) {
            $execution = $this->createExecution($workspace, $workflow, $user, $i);
            $this->createExecutionNodes($execution, rand(3, 6));
            $this->createExecutionLogs($execution, rand(5, 15));
        }

        $this->command->info("✅ Created {$executionCount} demo executions for OIT workspace!");
    }

    /**
     * Create a test execution
     */
    private function createExecution(Workspace $workspace, Workflow $workflow, User $user, int $index): Run
    {
        $hoursAgo = rand(1, 168); // Random time in last week
        $createdAt = now()->subHours($hoursAgo)->subMinutes(rand(0, 59));
        $startedAt = $createdAt->copy()->addSeconds(rand(1, 5));
        $finishedAt = $startedAt->copy()->addSeconds(rand(30, 180));

        return Run::create([
            'id' => Str::uuid(),
            'workspace_id' => $workspace->id,
            'workflow_id' => $workflow->id,
            'triggered_by' => $user->id,
            'status' => $this->randomStatus(),
            'mode' => $this->randomMode(),
            'trigger_data' => [
                'source' => 'oit_seeder',
                'index' => $index,
            ],
            'result_data' => [
                'success' => true,
                'message' => 'OIT demo execution completed',
            ],
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
            'credits_consumed' => rand(5, 30),
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
            $finishedAt = $startedAt->copy()->addSeconds(rand(5, 15));

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
                'input_data' => ['test' => true, 'index' => $i],
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
                'logged_at' => $execution->started_at->copy()->addSeconds($i * 3),
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
            ExecutionStatus::Completed,
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
