<?php

namespace App\Services\Agents\Knowledge\Readers;

use App\Enums\Agents\KnowledgeSourceType;
use App\Models\Agents\KnowledgeSource;
use App\Services\Agents\Knowledge\KnowledgeBatch;
use App\Services\Agents\Knowledge\KnowledgeDocumentData;
use App\Services\Assistant\Inbox\OutlookMailbox;

/**
 * Outlook mail in one folder, or matching a search (a sender, a subject
 * word). Each sync re-reads the newest messages and skips unchanged ones.
 */
class OutlookReader implements KnowledgeReader
{
    use ReadsThroughNodes;

    public function type(): KnowledgeSourceType
    {
        return KnowledgeSourceType::Outlook;
    }

    public function configRules(): array
    {
        return [
            'config.folder_id' => ['nullable', 'required_without:config.query', 'string', 'max:500'],
            'config.folder_name' => ['nullable', 'string', 'max:200'],
            'config.query' => ['nullable', 'required_without:config.folder_id', 'string', 'max:500'],
        ];
    }

    public function read(KnowledgeSource $source, int $limit): KnowledgeBatch
    {
        $mailbox = new OutlookMailbox($this->credential($source));

        $ids = filled($source->config['folder_id'] ?? null)
            ? $mailbox->folderMessageIds((string) $source->config['folder_id'], $limit)
            : $mailbox->searchIds((string) $source->config['query'], $limit);

        return new KnowledgeBatch(collect($ids)
            ->map(function (string $id) use ($mailbox): KnowledgeDocumentData {
                $message = $mailbox->message($id);

                return new KnowledgeDocumentData(
                    $id,
                    $message->subject ?: '(no subject)',
                    "From: {$message->from}\nTo: ".implode(', ', $message->to)."\nDate: {$message->receivedAt?->toDateTimeString()}\n\n{$message->body}",
                );
            })
            ->all());
    }
}
