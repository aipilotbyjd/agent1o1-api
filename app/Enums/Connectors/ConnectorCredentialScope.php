<?php

namespace App\Enums\Connectors;

/**
 * Gumloop's Personal vs Team connectors (see
 * docs/gumloop/output/raw/core-concepts/credentials.md), mapped onto this
 * project's single-workspace model: `Team` is this table's original
 * behavior (visible to any workspace member with `connector.view`),
 * `Personal` is private to `created_by`: hidden from every other member
 * regardless of role, including workspace owners/admins.
 *
 * `AiProviderCredential` uses the same two scopes with the same meaning:
 * see `Models\Concerns\HasScopedVisibility`.
 */
enum ConnectorCredentialScope: string
{
    case Team = 'team';
    case Personal = 'personal';
}
