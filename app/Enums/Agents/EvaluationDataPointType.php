<?php

namespace App\Enums\Agents;

/**
 * The shape of one value `SessionEvalJudgeAgent` is asked to extract from a
 * transcript. Purely descriptive: the judge returns JSON and nothing here
 * casts or validates the extracted value, since a data point returning
 * `null` (the judge couldn't find it) is expected and must not error.
 */
enum EvaluationDataPointType: string
{
    case String = 'string';
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Number = 'number';
}
