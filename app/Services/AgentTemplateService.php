<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentTemplate;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class AgentTemplateService
{
    public function __construct(private readonly AgentService $agentService) {}

    /**
     * Deploy a template into a workspace as a new, inactive agent.
     */
    public function deployToWorkspace(AgentTemplate $template, Workspace $workspace, User $creator): Agent
    {
        return DB::transaction(function () use ($template, $workspace, $creator) {
            $settings = $template->llm_settings ?? [];

            $agent = $this->agentService->create($workspace, $creator, [
                'name' => $template->name,
                'description' => $template->description,
                'instructions' => $template->system_prompt ?? $template->instructions ?? '',
                'model' => $template->llm_model,
                'provider' => $template->llm_provider,
                'max_steps' => $settings['max_steps'] ?? 15,
                'timeout_seconds' => $settings['timeout_seconds'] ?? 180,
                'is_active' => false,
                'metadata' => [
                    'created_from_template' => $template->id,
                    'template_name' => $template->name,
                ],
                'tools' => $template->tool_configs ?? [],
            ]);

            $template->increment('usage_count');

            return $agent;
        });
    }
}
