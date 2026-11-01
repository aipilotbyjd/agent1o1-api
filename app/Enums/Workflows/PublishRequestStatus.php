<?php

namespace App\Enums\Workflows;

/**
 * `workflow_publish_requests.status`. A request starts `Pending` and ends in
 * exactly one of the others: decided by a reviewer, taken back by the person
 * who asked, or replaced by a newer request for the same workflow.
 */
enum PublishRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Superseded = 'superseded';
}
