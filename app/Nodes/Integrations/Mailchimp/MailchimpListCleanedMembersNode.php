<?php

namespace App\Nodes\Integrations\Mailchimp;

class MailchimpListCleanedMembersNode extends AbstractMailchimpMemberTriggerNode
{
    public function type(): string
    {
        return 'mailchimp_list_cleaned_members';
    }

    public function name(): string
    {
        return 'Mailchimp: List Cleaned Members';
    }

    public function description(): string
    {
        return 'Lists the people Mailchimp removed after a bounce, newest first. As a trigger it starts the flow for each one.';
    }

    protected function status(): ?string
    {
        return 'cleaned';
    }

    protected function changedField(): string
    {
        return 'last_changed';
    }

    public function triggerName(): string
    {
        return 'Mailchimp: Member cleaned';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when Mailchimp cleans an address from an audience (it bounced).';
    }
}
