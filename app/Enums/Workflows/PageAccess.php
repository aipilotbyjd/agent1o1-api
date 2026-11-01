<?php

namespace App\Enums\Workflows;

/**
 * Who can open a workflow page. `Workspace` pages are used from inside the
 * app by members; the other two are served at a public link, `Password`
 * asking for a shared password first. Only `Workspace` is available without
 * `Feature::PublicPages`.
 */
enum PageAccess: string
{
    case Workspace = 'workspace';
    case Link = 'link';
    case Password = 'password';

    public function isPublic(): bool
    {
        return $this !== self::Workspace;
    }
}
