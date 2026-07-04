<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, User $creator, array $data): Agent
    {
        return DB::transaction(function () use ($workspace, $creator, $data) {
            $agent = Agent::create([
                'workspace_id' => $workspace->id,
                'created_by' => $creator->id,
                'name' => $data['name'],
                'slug' => $this->generateSlug($workspace, $data['name']),
                'description' => $data['description'] ?? null,
                'instructions' => $data['instructions'],
                'model' => $data['model'] ?? 'claude-sonnet-4-6',
                'provider' => $data['provider'] ?? 'anthropic',
                'max_steps' => $data['max_steps'] ?? 15,
                'timeout_seconds' => $data['timeout_seconds'] ?? 180,
                'is_active' => $data['is_active'] ?? true,
                'metadata' => $data['metadata'] ?? null,
                'default_workflow_id' => $data['default_workflow_id'] ?? null,
            ]);

            if (! empty($data['tools'])) {
                $this->syncToolConfigs($agent, $data['tools']);
            }

            return $agent->fresh(['toolConfigs', 'skills']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Agent $agent, array $data): Agent
    {
        return DB::transaction(function () use ($agent, $data) {
            if (isset($data['name']) && $data['name'] !== $agent->name) {
                $data['slug'] = $this->generateSlug($agent->workspace, $data['name'], $agent->id);
            }

            $agent->update(collect($data)->only([
                'name', 'slug', 'description', 'instructions', 'model', 'provider',
                'max_steps', 'timeout_seconds', 'is_active', 'metadata', 'default_workflow_id',
            ])->all());

            if (array_key_exists('tools', $data)) {
                $this->syncToolConfigs($agent, $data['tools'] ?? []);
            }

            return $agent->fresh(['toolConfigs', 'skills']);
        });
    }

    public function delete(Agent $agent): void
    {
        $agent->delete();
    }

    public function duplicate(Agent $agent, User $creator): Agent
    {
        return DB::transaction(function () use ($agent, $creator) {
            $agent->loadMissing('toolConfigs', 'skills');

            $copy = Agent::create([
                'workspace_id' => $agent->workspace_id,
                'created_by' => $creator->id,
                'name' => "{$agent->name} (Copy)",
                'slug' => $this->generateSlug($agent->workspace, "{$agent->name} (Copy)"),
                'description' => $agent->description,
                'instructions' => $agent->instructions,
                'model' => $agent->model,
                'provider' => $agent->provider,
                'max_steps' => $agent->max_steps,
                'timeout_seconds' => $agent->timeout_seconds,
                'is_active' => false,
                'metadata' => $agent->metadata,
                'default_workflow_id' => $agent->default_workflow_id,
            ]);

            foreach ($agent->toolConfigs as $config) {
                $copy->toolConfigs()->create($config->only([
                    'node_type', 'tool_name', 'tool_description', 'is_enabled', 'sort_order',
                ]));
            }

            $copy->skills()->sync(
                $agent->skills->mapWithKeys(fn ($skill) => [
                    $skill->id => ['sort_order' => $skill->pivot->sort_order],
                ])->all()
            );

            return $copy->fresh(['toolConfigs', 'skills']);
        });
    }

    /**
     * Replace the agent's tool configs with the given set.
     *
     * @param  array<int, array<string, mixed>>  $tools
     */
    public function syncToolConfigs(Agent $agent, array $tools): void
    {
        $agent->toolConfigs()->delete();

        foreach ($tools as $index => $tool) {
            $agent->toolConfigs()->create([
                'node_type' => $tool['node_type'],
                'tool_name' => $tool['tool_name'] ?? Str::of($tool['node_type'])->slug('_')->toString(),
                'tool_description' => $tool['tool_description'] ?? '',
                'is_enabled' => $tool['is_enabled'] ?? true,
                'sort_order' => $tool['sort_order'] ?? $index,
            ]);
        }
    }

    private function generateSlug(Workspace $workspace, string $name, ?string $excludeId = null): string
    {
        $base = Str::slug($name) ?: 'agent';
        $slug = $base;
        $suffix = 1;

        while (Agent::withTrashed()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
