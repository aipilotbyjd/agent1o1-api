<?php

namespace App\Services\Workflows\NodeOptions;

use App\Models\User;
use App\Models\Workspaces\Workspace;

/**
 * One dropdown load: what the editor already has filled in on the node
 * (`config`, for dependent fields such as a spreadsheet's tabs), what the
 * member typed into the dropdown (`search`), and which page (`cursor`).
 * `token` is the connected account's access token, or null for sources that
 * read the workspace itself (agents, workflows, models). `credentialData` is
 * the account's stored fields, for apps that log in with more than a token.
 */
final readonly class NodeOptionsQuery
{
    /**
     * Trimmed; null when the member hasn't typed anything.
     */
    public ?string $search;

    public ?string $cursor;

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $credentialData
     */
    public function __construct(
        public Workspace $workspace,
        public User $user,
        public array $config,
        public ?string $token = null,
        ?string $search = null,
        ?string $cursor = null,
        public array $credentialData = [],
    ) {
        $search = $search !== null ? trim($search) : null;
        $this->search = $search !== '' ? $search : null;
        $this->cursor = $cursor !== null && $cursor !== '' ? $cursor : null;
    }

    /**
     * A config value as a string, or `$default` when it's unset, empty, or
     * not a scalar.
     */
    public function configString(string $key, ?string $default = null): ?string
    {
        $value = $this->config[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }

    /**
     * Case-insensitive "does any of these match what the member typed", for
     * providers whose list APIs can't search server-side.
     */
    public function matches(string ...$haystacks): bool
    {
        if ($this->search === null) {
            return true;
        }

        foreach ($haystacks as $haystack) {
            if (mb_stripos($haystack, $this->search) !== false) {
                return true;
            }
        }

        return false;
    }
}
