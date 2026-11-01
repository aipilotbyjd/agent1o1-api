<?php

namespace App\Enums\Broadcasts;

/**
 * One recipient's delivery. `Sending` is the claim a worker takes before it
 * mails, so a retried job can never mail the same person twice.
 */
enum BroadcastRecipientStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
