<?php

namespace App\Nodes\Integrations\Pipedrive;

class PipedriveListNewPeopleNode extends AbstractPipedriveRecordTriggerNode
{
    public function type(): string
    {
        return 'pipedrive_list_new_persons';
    }

    public function name(): string
    {
        return 'Pipedrive: List New People';
    }

    public function description(): string
    {
        return 'Lists the persons added lately, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function collection(): string
    {
        return 'persons';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Pipedrive: New person';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a person is added in Pipedrive. Instant, with a check every few minutes.';
    }
}
