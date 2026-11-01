<?php

namespace App\Enums\Assistant;

/**
 * Where a conversation started. Every channel runs the same assistant; the
 * origin only decides how replies are formatted and how follow-ups find
 * their way back to this session (`external_thread_ref`).
 */
enum AssistantSessionOrigin: string
{
    case Web = 'web';
    case Slack = 'slack';
    case Email = 'email';
    case Sms = 'sms';
    case Task = 'task';
    case Trigger = 'trigger';
}
