<?php

namespace App\Enums\Artifacts;

/**
 * How broadly an artifact group is visible, mirroring Gumloop's General
 * Access levels (see docs/gumloop/output/raw/core-concepts/agent_artifacts.md).
 * This app has no anonymous/public request path, so `Organization` and
 * `Anyone` both resolve to "any workspace member with artifact.view",
 * only `Restricted` narrows access below that, to the creator and explicit
 * `ArtifactShare` grants.
 */
enum ArtifactGeneralAccess: string
{
    case Restricted = 'restricted';
    case Organization = 'organization';
    case Anyone = 'anyone';
}
