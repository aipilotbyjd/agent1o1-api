<?php

namespace App\Services\Assistant\Branding;

use App\Models\Workspaces\Workspace;

/**
 * Reads the brand from `config/assistant.php` (and so from `ASSISTANT_*`
 * env vars). Read on every call rather than memoised, so a config change in
 * a long-running worker or a test override is picked up immediately.
 */
class ConfigBrandRepository implements BrandRepository
{
    public function current(?Workspace $workspace = null): Brand
    {
        /** @var array<string, mixed> $config */
        $config = config('assistant.brand');

        return new Brand(
            name: (string) $config['name'],
            tagline: (string) $config['tagline'],
            description: (string) $config['description'],
            emoji: (string) $config['emoji'],
            color: (string) $config['color'],
            iconUrl: (string) $config['icon_url'],
            avatarUrl: (string) $config['avatar_url'],
            features: array_map('strval', (array) $config['features']),
            emailLocalPart: (string) $config['email_local_part'],
            emailAliases: array_values(array_map('strval', (array) $config['email_aliases'])),
            inboundDomain: (string) $config['inbound_domain'],
            smsSignature: (string) $config['sms_signature'],
        );
    }
}
