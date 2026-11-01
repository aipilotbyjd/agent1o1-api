<?php

namespace App\Nodes\Integrations\Airtable;

class AirtableListUpdatedRecordsNode extends AbstractAirtableRecordTriggerNode
{
    public function type(): string
    {
        return 'airtable_list_updated_records';
    }

    public function name(): string
    {
        return 'Airtable: List Changed Records';
    }

    public function description(): string
    {
        return 'Lists the records changed lately in a table (a record changed twice appears as two events). As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Airtable: Record changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a record in a table is changed, or added. Instant, with a check every few minutes.';
    }
}
