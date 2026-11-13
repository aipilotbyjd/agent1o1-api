<?php

namespace App\Enums\Sandbox;

/**
 * How code is run: on a computer that lasts for a conversation, or once on
 * a fresh one that is deleted straight after.
 */
enum SandboxMode: string
{
    case Persistent = 'persistent';
    case OneShot = 'one_shot';
}
