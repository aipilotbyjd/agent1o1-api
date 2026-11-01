<?php

namespace App\Enums\Agents;

/**
 * What kind of quality rule one evaluation criterion checks: purely
 * descriptive, for grouping criteria in the UI. Grading itself only ever
 * looks at `EvaluationCriterionAction`.
 */
enum EvaluationCriterionType: string
{
    case ProhibitedAction = 'prohibited_action';
    case ProhibitedWords = 'prohibited_words';
    case VoiceTone = 'voice_tone';
    case Other = 'other';
}
