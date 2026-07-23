<?php

namespace App\Contracts;

/**
 * A workspace-scoped entity that can be the target of a Trigger and produce
 * runs — currently a Workflow or an Agent. The polymorphic `target` relation
 * on Trigger/TriggerEvent points at implementors of this contract.
 *
 * Backed by Eloquent, so getKey()/getMorphClass() are already provided by the
 * base Model; this interface documents intent and lets services type-hint a
 * unified automation target.
 */
interface Automatable
{
    /** The primary key of the target. */
    public function getKey();

    /** The morph alias for the target ('workflow' | 'agent'). */
    public function getMorphClass();
}
