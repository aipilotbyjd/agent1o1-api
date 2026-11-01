<?php

namespace App\Services\SuperAdmin\Audit;

use App\Models\SuperAdmin\SuperAdminAuditLog;

/**
 * How an audit entry is hashed and linked to the one before it.
 *
 * The hash is an HMAC (keyed with a value derived from `APP_KEY`) over the
 * entry's content and the previous entry's hash. Someone who can only write to
 * the database can change a row but cannot recompute the chain after it, so
 * the change shows. Someone who also holds the application key can; for that,
 * keep the latest hash somewhere else and compare it (see `AuditChainVerifier`).
 *
 * The content is encoded canonically (object keys sorted, a fixed field
 * order) after a round trip through JSON, so the hash computed when an entry is
 * written equals the one computed from what the database hands back later.
 */
final class AuditChain
{
    /**
     * @return array<int, string>
     */
    private const array FIELDS = [
        'sequence', 'previous_hash', 'super_admin_id', 'actor_label', 'action', 'subject_type', 'subject_id',
        'before', 'after', 'reason', 'ip_address', 'user_agent', 'request_id',
    ];

    public static function hash(SuperAdminAuditLog $entry): string
    {
        $content = [];

        foreach (self::FIELDS as $field) {
            $content[] = $entry->getAttribute($field);
        }

        $content[] = $entry->created_at?->format('Y-m-d H:i:s');

        return hash_hmac('sha256', self::encode($content), self::key());
    }

    private static function encode(mixed $value): string
    {
        return json_encode(self::canonical(json_decode(json_encode($value, JSON_THROW_ON_ERROR), true)), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::canonical(...), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private static function key(): string
    {
        return 'super-admin-audit-chain|'.config('app.key');
    }
}
