<?php

namespace App\Enums\Agents;

/**
 * What a failing criterion does to the session's grade:
 * `SessionEvaluationGrader`'s only input besides sentiment and call outcome.
 * `Flag` marks the session for review; `Notify` additionally pages the
 * workspace's owners/admins the moment it fires. Use `Notify` for rules that
 * must never be broken, `Flag` for quality standards that aren't urgent.
 */
enum EvaluationCriterionAction: string
{
    case Flag = 'flag';
    case Notify = 'notify';
}
