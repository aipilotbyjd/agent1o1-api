<?php

namespace App\Nodes\Integrations\Pipedrive;

class PipedriveListChangedDealsNode extends AbstractPipedriveRecordTriggerNode
{
    public function type(): string
    {
        return 'pipedrive_list_changed_deals';
    }

    public function name(): string
    {
        return 'Pipedrive: List Changed Deals';
    }

    public function description(): string
    {
        return 'Lists the deals changed lately, most recent first (one changed twice appears as two events). As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function collection(): string
    {
        return 'deals';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Pipedrive: Deal changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a deal is changed in Pipedrive, or added. Instant, with a check every few minutes.';
    }
}
