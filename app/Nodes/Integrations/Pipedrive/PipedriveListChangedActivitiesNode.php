<?php

namespace App\Nodes\Integrations\Pipedrive;

class PipedriveListChangedActivitiesNode extends AbstractPipedriveRecordTriggerNode
{
    public function type(): string
    {
        return 'pipedrive_list_changed_activities';
    }

    public function name(): string
    {
        return 'Pipedrive: List Changed Activities';
    }

    public function description(): string
    {
        return 'Lists the activities changed lately, most recent first (one changed twice appears as two events). As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function collection(): string
    {
        return 'activities';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Pipedrive: Activity changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an activity is changed in Pipedrive, or added. Instant, with a check every few minutes.';
    }
}
