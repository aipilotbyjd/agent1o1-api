<?php

namespace App\Services\Agents;

use App\Enums\Agents\SessionEvaluationGrade;

/**
 * Turns the judge's raw verdict into a `SessionEvaluationGrade`, in code,
 * never the LLM. Mirrors Gumloop's published, deterministic rule
 * (docs/gumloop/output/raw/core-concepts/evaluations.md's "Grade computation
 * logic"), the one part of that feature copied faithfully because it is a
 * good, auditable rule: the same inputs always produce the same grade,
 * independently of anything the judge model felt like emphasizing.
 */
class SessionEvaluationGrader
{
    /**
     * @param  array<int, array{id: string, result: string}>  $criteriaResults
     * @param  array<int, array{id: string, priority: string}>  $criteria  configured criteria, for looking up each result's priority
     */
    public function grade(
        array $criteriaResults,
        array $criteria,
        string $callSuccessful,
        ?string $sentiment,
        bool $sentimentAffectsGrade,
    ): SessionEvaluationGrade {
        $priorityById = collect($criteria)->keyBy('id');

        $failures = array_filter($criteriaResults, fn (array $result): bool => ($result['result'] ?? null) === 'failure');

        foreach ($failures as $failure) {
            $priority = $priorityById->get($failure['id'] ?? null)['priority'] ?? null;

            if ($priority === 'notify') {
                return SessionEvaluationGrade::NeedsAttention;
            }
        }

        if (! empty($failures)) {
            return SessionEvaluationGrade::NeedsReview;
        }

        if ($callSuccessful === 'failure') {
            return SessionEvaluationGrade::NeedsReview;
        }

        if ($sentimentAffectsGrade && $sentiment === 'negative') {
            return SessionEvaluationGrade::NeedsReview;
        }

        return SessionEvaluationGrade::Pass;
    }
}
