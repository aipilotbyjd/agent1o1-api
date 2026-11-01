<?php

namespace App\Services\SuperAdmin\Audit;

use App\Models\SuperAdmin\SuperAdminAuditLog;

/**
 * Walks the whole audit log in order and checks the chain: no entry missing
 * (the sequence has no gaps), none changed (each hash matches its content),
 * none slipped in (every entry is chained to the one before it).
 *
 * It cannot see entries cut off the *end* of the log, since nothing after
 * them points back. For that, keep the `head_hash` it reports somewhere the
 * database's writers cannot reach, and pass it as `$expectedHead` later.
 */
class AuditChainVerifier
{
    /**
     * @return array{intact: bool, checked: int, head_sequence: ?int, head_hash: ?string, break: ?array{sequence: ?int, id: string, reason: string, message: string}}
     */
    public function verify(?string $expectedHead = null): array
    {
        $expectedSequence = 1;
        $previousHash = null;
        $checked = 0;
        $break = null;

        foreach (SuperAdminAuditLog::query()->orderByRaw('sequence is null desc')->orderBy('sequence')->orderBy('created_at')->cursor() as $entry) {
            $problem = $this->problem($entry, $expectedSequence, $previousHash);

            if ($problem !== null) {
                $break = ['sequence' => $entry->sequence, 'id' => $entry->id, ...$problem];

                break;
            }

            $checked++;
            $expectedSequence++;
            $previousHash = $entry->hash;
        }

        if ($break === null && $expectedHead !== null && ! hash_equals($expectedHead, (string) $previousHash)) {
            $break = [
                'sequence' => $expectedSequence - 1,
                'id' => '',
                'reason' => 'head_mismatch',
                'message' => 'The newest entry is not the one you recorded: entries may have been removed from the end of the log.',
            ];
        }

        return [
            'intact' => $break === null,
            'checked' => $checked,
            'head_sequence' => $checked > 0 ? $expectedSequence - 1 : null,
            'head_hash' => $previousHash,
            'break' => $break,
        ];
    }

    /**
     * @return array{reason: string, message: string}|null
     */
    private function problem(SuperAdminAuditLog $entry, int $expectedSequence, ?string $previousHash): ?array
    {
        if ($entry->sequence === null) {
            return ['reason' => 'unchained_entry', 'message' => 'An entry was written without being chained to the log.'];
        }

        if ($entry->sequence !== $expectedSequence) {
            return ['reason' => 'sequence_gap', 'message' => "Entry {$expectedSequence} is missing: the log jumps to {$entry->sequence}."];
        }

        if (($entry->previous_hash ?? null) !== $previousHash) {
            return ['reason' => 'previous_hash_mismatch', 'message' => "Entry {$entry->sequence} is not chained to the entry before it."];
        }

        if (! hash_equals(AuditChain::hash($entry), (string) $entry->hash)) {
            return ['reason' => 'hash_mismatch', 'message' => "Entry {$entry->sequence} was changed after it was written."];
        }

        return null;
    }
}
