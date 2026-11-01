<?php

namespace App\Nodes\Integrations\Pipedrive;

class PipedriveListChangedPeopleNode extends AbstractPipedriveRecordTriggerNode
{
    public function type(): string
    {
        return 'pipedrive_list_changed_persons';
    }

    public function name(): string
    {
        return 'Pipedrive: List Changed People';
    }

    public function description(): string
    {
        return 'Lists the persons changed lately, most recent first (one changed twice appears as two events). As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function collection(): string
    {
        return 'persons';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Pipedrive: Person changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a person is changed in Pipedrive, or added. Instant, with a check every few minutes.';
    }
}
