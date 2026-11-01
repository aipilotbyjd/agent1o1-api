<?php

namespace App\Nodes\Integrations\Pipedrive;

class PipedriveListNewDealsNode extends AbstractPipedriveRecordTriggerNode
{
    public function type(): string
    {
        return 'pipedrive_list_new_deals';
    }

    public function name(): string
    {
        return 'Pipedrive: List New Deals';
    }

    public function description(): string
    {
        return 'Lists the deals added lately, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function collection(): string
    {
        return 'deals';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Pipedrive: New deal';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a deal is added in Pipedrive. Instant, with a check every few minutes.';
    }
}
