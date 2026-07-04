<?php

namespace App\Agents\Skills;

use App\Models\AgentSkill;

/**
 * Selects the most relevant skills for a given message using keyword scoring.
 * Keeps token usage low by avoiding injection of all skills into every prompt.
 */
class SkillContextBuilder
{
    private const MAX_INLINE_SKILLS = 3;

    private const MAX_SCORED_SKILLS = 5;

    /**
     * @param  AgentSkill[]  $skills
     * @return AgentSkill[]
     */
    public function select(array $skills, string $message): array
    {
        if (empty($skills)) {
            return [];
        }

        if (count($skills) <= self::MAX_INLINE_SKILLS) {
            return $skills;
        }

        $scored = [];

        foreach ($skills as $skill) {
            $scored[] = [
                'skill' => $skill,
                'score' => $this->score($skill, $message),
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(
            fn ($item) => $item['skill'],
            array_slice($scored, 0, self::MAX_SCORED_SKILLS),
        );
    }

    private function score(AgentSkill $skill, string $message): int
    {
        $messageLower = strtolower($message);
        $score = 0;

        $skillText = implode(' ', array_filter([
            strtolower($skill->name),
            strtolower($skill->description ?? ''),
            strtolower(substr($skill->instructions, 0, 500)),
        ]));

        $keywords = array_filter(preg_split('/\W+/', $skillText) ?: []);

        foreach ($keywords as $keyword) {
            if (strlen($keyword) > 3 && str_contains($messageLower, $keyword)) {
                $score++;
            }
        }

        return $score;
    }
}
