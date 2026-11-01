<?php

namespace App\Services\Agents\Knowledge\Readers;

use App\Enums\Agents\KnowledgeSourceType;
use App\Models\Agents\KnowledgeSource;
use App\Services\Agents\Knowledge\KnowledgeBatch;
use App\Services\Agents\Knowledge\KnowledgeDocumentData;
use App\Services\Assistant\Inbox\GmailMailbox;

/**
 * Email under a Gmail label, or matching a Gmail search.
 * Each sync reads mail newer than the last one.
 */
class GmailReader implements KnowledgeReader
{
    use ReadsThroughNodes;

    public function type(): KnowledgeSourceType
    {
        return KnowledgeSourceType::Gmail;
    }

    public function configRules(): array
    {
        return [
            'config.label' => ['nullable', 'required_without:config.query', 'string', 'max:200'],
            'config.query' => ['nullable', 'required_without:config.label', 'string', 'max:500'],
        ];
    }

    public function read(KnowledgeSource $source, int $limit): KnowledgeBatch
    {
        $startedAt = (string) now()->getTimestamp();
        $query = trim($this->search($source).($source->sync_cursor !== null ? " after:{$source->sync_cursor}" : ''));
        $mailbox = new GmailMailbox($this->credential($source));

        $documents = collect($mailbox->searchIds($query, $limit))
            ->map(function (string $id) use ($mailbox): KnowledgeDocumentData {
                $message = $mailbox->message($id);

                return new KnowledgeDocumentData(
                    $id,
                    $message->subject ?: '(no subject)',
                    "From: {$message->from}\nTo: ".implode(', ', $message->to)."\nDate: {$message->receivedAt?->toDateTimeString()}\n\n{$message->body}",
                    "https://mail.google.com/mail/u/0/#all/{$id}",
                );
            })
            ->all();

        return new KnowledgeBatch($documents, count($documents) < $limit ? $startedAt : null);
    }

    /**
     * A picked label, as Gmail search writes it (spaces and slashes become
     * hyphens), plus any extra search terms.
     */
    private function search(KnowledgeSource $source): string
    {
        $label = trim((string) ($source->config['label'] ?? ''));

        return trim(($label !== '' ? 'label:'.preg_replace('/[\s\/]+/', '-', mb_strtolower($label)) : '').' '.($source->config['query'] ?? ''));
    }
}
