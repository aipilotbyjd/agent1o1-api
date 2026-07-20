<?php

namespace App\Services\AgentSkill;

use App\Agents\Internal\SkillGeneratorAgent;
use App\Models\AiGenerationLog;
use App\Models\User;
use App\Models\Workspace;

class SkillGenerationService
{
    private const CATEGORIES = [
        'General', 'Research', 'Data', 'Communication', 'Automation', 'Development', 'Content',
    ];

    /**
     * Generate a skill draft from a natural-language prompt.
     *
     * @return array{name: string, description: string|null, category: string, instructions: string}
     */
    public function generate(Workspace $workspace, string $prompt, ?User $user = null): array
    {
        $response = (new SkillGeneratorAgent)->prompt($prompt);

        AiGenerationLog::create([
            'workspace_id' => $workspace->id,
            'created_by' => $user?->id,
            'type' => 'skill_generate',
            'prompt_summary' => str($prompt)->limit(200)->toString(),
        ]);

        return [
            'name' => $response['name'] ?? 'Generated Skill',
            'description' => $response['description'] ?? null,
            'category' => in_array($response['category'] ?? null, self::CATEGORIES, true)
                ? $response['category']
                : 'General',
            'instructions' => $response['instructions'] ?? '',
        ];
    }
}
