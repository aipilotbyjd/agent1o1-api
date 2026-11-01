<?php

namespace App\Nodes\Integrations\Mailchimp;

class MailchimpListNewSubscribersNode extends AbstractMailchimpMemberTriggerNode
{
    public function type(): string
    {
        return 'mailchimp_list_new_subscribers';
    }

    public function name(): string
    {
        return 'Mailchimp: List New Subscribers';
    }

    public function description(): string
    {
        return 'Lists the people who subscribed lately, newest first. As a trigger it starts the flow for each new subscriber.';
    }

    protected function status(): ?string
    {
        return 'subscribed';
    }

    protected function changedField(): string
    {
        return 'timestamp_opt';
    }

    public function triggerName(): string
    {
        return 'Mailchimp: New subscriber';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone subscribes to a Mailchimp audience.';
    }
}
