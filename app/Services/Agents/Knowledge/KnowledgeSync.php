<?php

namespace App\Services\Agents\Knowledge;

use App\Enums\Agents\KnowledgeSourceStatus;
use App\Models\Agents\KnowledgeSource;
use App\Services\Agents\KnowledgeBase;
use App\Services\Billing\CreditGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Brings a synced source's documents up to date in the knowledge base. A
 * document whose text hasn't changed is left alone (no re-embedding, no
 * charge). A failed sync keeps what the source already had and its old
 * cursor, so the next one picks up where this should have.
 */
class KnowledgeSync
{
    public function __construct(
        private readonly KnowledgeReaders $readers,
        private readonly KnowledgeBase $knowledge,
        private readonly CreditGate $creditGate,
    ) {}

    /**
     * @return int documents (re)indexed
     */
    public function sync(KnowledgeSource $source): int
    {
        $source->forceFill(['status' => KnowledgeSourceStatus::Syncing, 'last_error' => null])->save();
        $changed = 0;

        try {
            $this->creditGate->assertCanStartRun($source->workspace);

            $batch = $this->readers->for($source->type)->read($source, (int) config('knowledge_base.sources.max_documents_per_sync'));

            foreach ($batch->documents as $document) {
                $changed += $this->put($source, $document) ? 1 : 0;
            }

            if ($batch->complete) {
                $source->chunks()->whereNotIn('external_id', array_map(fn (KnowledgeDocumentData $document): string => $document->externalId, $batch->documents))->delete();
            }

            $source->forceFill([
                'status' => KnowledgeSourceStatus::Ready,
                'sync_cursor' => $batch->cursor ?? $source->sync_cursor,
                'last_synced_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            report($e);
            $source->forceFill(['status' => KnowledgeSourceStatus::Failed, 'last_error' => Str::limit($e->getMessage(), 500)])->save();
        }

        $source->forceFill([
            'documents_count' => $source->chunks()->distinct()->count('external_id'),
            'chunks_count' => $source->chunks()->count(),
        ])->save();

        return $changed;
    }

    /**
     * @return bool whether the document was (re)indexed
     */
    public function put(KnowledgeSource $source, KnowledgeDocumentData $document): bool
    {
        $externalId = mb_substr($document->externalId, 0, 250);
        $text = Str::limit(trim($document->text), (int) config('knowledge_base.sources.max_document_chars'), '');
        $hash = hash('sha256', $document->title."\n".$text);

        $existing = $source->chunks()->where('external_id', $externalId);
        $current = (clone $existing)->first();

        if ($current !== null && ($current->metadata['content_hash'] ?? null) === $hash) {
            return false;
        }

        DB::transaction(function () use ($source, $existing, $document, $externalId, $text, $hash): void {
            $existing->delete();

            if ($text === '') {
                return;
            }

            $this->knowledge->ingest(
                $source->workspace,
                $text,
                Str::limit($document->title ?: 'Untitled', 250),
                $source->collection,
                array_filter(['url' => $document->url, 'content_hash' => $hash, 'source_name' => $source->name]),
                ownerId: $source->owner_id,
                knowledgeSourceId: $source->id,
                externalId: $externalId,
            );
        });

        return true;
    }
}
