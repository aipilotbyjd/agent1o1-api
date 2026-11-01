<?php

namespace App\Nodes\Integrations\Pipedrive;

class PipedriveListNewActivitiesNode extends AbstractPipedriveRecordTriggerNode
{
    public function type(): string
    {
        return 'pipedrive_list_new_activities';
    }

    public function name(): string
    {
        return 'Pipedrive: List New Activities';
    }

    public function description(): string
    {
        return 'Lists the activities added lately, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function collection(): string
    {
        return 'activities';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Pipedrive: New activity';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an activity is added in Pipedrive. Instant, with a check every few minutes.';
    }
}
