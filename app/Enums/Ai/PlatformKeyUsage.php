<?php

namespace App\Enums\Ai;

/**
 * When a workspace's AI calls may run on the platform's own keys (and so
 * cost token credits): see `WorkspaceAiKeyPolicy`.
 */
enum PlatformKeyUsage: string
{
    /** The workspace's key first, the platform's behind it as a backup. */
    case Fallback = 'fallback';

    /** The platform's key only for models none of the workspace's keys cover. */
    case WhenNoKey = 'when_no_key';

    /** Never: a model none of the workspace's keys cover can't be used. */
    case Never = 'never';
}
