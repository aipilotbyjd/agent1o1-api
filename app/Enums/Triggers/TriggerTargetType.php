<?php

namespace App\Enums\Triggers;

use App\Models\Agents\Agent;
use App\Models\Workflows\Workflow;
use Illuminate\Database\Eloquent\Model;

/**
 * What a trigger starts.
 *
 * The values are registered in the morph map, so `triggers.target_type`
 * stores `workflow` or `agent` rather than a class name.
 *
 * @see AppServiceProvider::configureMorphMap()
 */
enum TriggerTargetType: string
{
    /**
     * Starts a run of the workflow's published version.
     */
    case Workflow = 'workflow';

    /**
     * Starts a fresh agent session with one message.
     */
    case Agent = 'agent';

    /**
     * The model class the morph map aliases this value to.
     *
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Workflow => Workflow::class,
            self::Agent => Agent::class,
        };
    }
}
