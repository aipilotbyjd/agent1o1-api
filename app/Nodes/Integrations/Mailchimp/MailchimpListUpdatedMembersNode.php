<?php

namespace App\Nodes\Integrations\Mailchimp;

class MailchimpListUpdatedMembersNode extends AbstractMailchimpMemberTriggerNode
{
    public function type(): string
    {
        return 'mailchimp_list_updated_members';
    }

    public function name(): string
    {
        return 'Mailchimp: List Updated Members';
    }

    public function description(): string
    {
        return 'Lists the people whose details changed lately (profile, status, tags), newest change first. As a trigger it starts the flow for each change.';
    }

    protected function status(): ?string
    {
        return null;
    }

    protected function changedField(): string
    {
        return 'last_changed';
    }

    public function triggerName(): string
    {
        return 'Mailchimp: Member updated';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a person on a Mailchimp audience is added or changes (profile, status or tags).';
    }
}
