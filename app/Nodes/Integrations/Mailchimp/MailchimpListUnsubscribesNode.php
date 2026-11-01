<?php

namespace App\Nodes\Integrations\Mailchimp;

class MailchimpListUnsubscribesNode extends AbstractMailchimpMemberTriggerNode
{
    public function type(): string
    {
        return 'mailchimp_list_unsubscribes';
    }

    public function name(): string
    {
        return 'Mailchimp: List Unsubscribes';
    }

    public function description(): string
    {
        return 'Lists the people who unsubscribed lately, newest first. As a trigger it starts the flow for each unsubscribe.';
    }

    protected function status(): ?string
    {
        return 'unsubscribed';
    }

    protected function changedField(): string
    {
        return 'last_changed';
    }

    public function triggerName(): string
    {
        return 'Mailchimp: Unsubscribed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone unsubscribes from a Mailchimp audience.';
    }
}
