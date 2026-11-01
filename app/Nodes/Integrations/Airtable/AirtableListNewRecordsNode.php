<?php

namespace App\Nodes\Integrations\Airtable;

class AirtableListNewRecordsNode extends AbstractAirtableRecordTriggerNode
{
    public function type(): string
    {
        return 'airtable_list_new_records';
    }

    public function name(): string
    {
        return 'Airtable: List New Records';
    }

    public function description(): string
    {
        return 'Lists the records added lately to a table, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Airtable: New record';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a record is added to a table. Instant, with a check every few minutes.';
    }
}
