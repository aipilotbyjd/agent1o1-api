<?php

namespace App\Enums\System;

/**
 * What started a backup: the scheduler, or a super admin pressing "Back up now".
 */
enum BackupType: string
{
    case Scheduled = 'scheduled';
    case Manual = 'manual';
}
