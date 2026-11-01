<?php

namespace App\Nodes\Integrations\ZohoBooks;

class ZohoBooksListNewCustomersNode extends AbstractZohoBooksRecordTriggerNode
{
    public function type(): string
    {
        return 'zoho_books_list_new_customers';
    }

    public function name(): string
    {
        return 'Zoho Books: List New Customers';
    }

    public function description(): string
    {
        return 'Lists the customers added lately, newest first. As a trigger it starts the flow for each one. Checked every few minutes.';
    }

    protected function path(): string
    {
        return '/contacts';
    }

    protected function collection(): string
    {
        return 'contacts';
    }

    protected function filters(): array
    {
        return ['contact_type' => 'customer', 'filter_by' => 'Status.All'];
    }

    protected function shape(array $record): array
    {
        return $this->shapeContact($record);
    }

    public function triggerName(): string
    {
        return 'Zoho Books: New customer';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a customer is added in Zoho Books. Checked every few minutes.';
    }
}
