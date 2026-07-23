<?php

namespace Database\Factories;

use App\Models\AiFixSuggestion;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiFixSuggestion>
 */
class AiFixSuggestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'execution_id' => Run::factory(),
            'workspace_id' => Workspace::factory(),
            'node_id' => 'node-'.fake()->numerify('###'),
            'node_type' => 'http_request',
            'diagnosis' => fake()->sentence(),
            'suggestions' => [
                ['title' => 'Fix the URL', 'description' => 'The URL was malformed.', 'fix_config' => ['url' => 'https://api.test']],
            ],
            'status' => 'pending',
        ];
    }
}
