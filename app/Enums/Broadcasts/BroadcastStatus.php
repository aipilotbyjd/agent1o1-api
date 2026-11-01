<?php

namespace App\Enums\Broadcasts;

/**
 * Where an email campaign is in its life: draft → scheduled → sending → sent,
 * or canceled / failed. Only a draft can be edited.
 */
enum BroadcastStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Sent = 'sent';
    case Canceled = 'canceled';
    case Failed = 'failed';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function canStart(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled], true);
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled, self::Sending], true);
    }

    public function canDelete(): bool
    {
        return in_array($this, [self::Draft, self::Canceled], true);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Sent, self::Canceled, self::Failed], true);
    }
}
