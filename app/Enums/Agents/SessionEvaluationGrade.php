<?php

namespace App\Enums\Agents;

/**
 * The outcome of one `AgentSessionEvaluation`, computed deterministically by
 * `Services\Agents\SessionEvaluationGrader`, never by the judge model
 * itself. Values match the Gumloop API vocabulary
 * (docs/gumloop/output/raw/core-concepts/evaluations.md's "Grades" table) so
 * the mental model transfers directly: `pass` needs nothing, `needs_review`
 * is worth a look, `needs_attention` pages the workspace's owners/admins.
 */
enum SessionEvaluationGrade: string
{
    case Pass = 'pass';
    case NeedsReview = 'needs_review';
    case NeedsAttention = 'needs_attention';
}
