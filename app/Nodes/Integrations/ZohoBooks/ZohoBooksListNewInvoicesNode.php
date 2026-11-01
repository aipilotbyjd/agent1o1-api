<?php

namespace App\Nodes\Integrations\ZohoBooks;

class ZohoBooksListNewInvoicesNode extends AbstractZohoBooksRecordTriggerNode
{
    public function type(): string
    {
        return 'zoho_books_list_new_invoices';
    }

    public function name(): string
    {
        return 'Zoho Books: List New Invoices';
    }

    public function description(): string
    {
        return 'Lists the invoices created lately, newest first. As a trigger it starts the flow for each one. Checked every few minutes.';
    }

    protected function path(): string
    {
        return '/invoices';
    }

    protected function collection(): string
    {
        return 'invoices';
    }

    protected function shape(array $record): array
    {
        return $this->shapeInvoice($record);
    }

    public function triggerName(): string
    {
        return 'Zoho Books: New invoice';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an invoice is created in Zoho Books. Checked every few minutes.';
    }
}
