<?php

namespace App\Services\Assistant\Inbox;

use Carbon\CarbonInterface;

/**
 * Everything Smart Inbox does to a mailbox: Gmail and Outlook implement
 * it, so classifying and drafting stay provider-free. `INBOX` is the one
 * shared label id: removing it archives the message.
 */
interface Mailbox
{
    public function ownAddress(): string;

    /**
     * @return list<string> ids of inbox messages received after `$since`
     */
    public function newMessageIds(CarbonInterface $since, int $limit): array;

    public function message(string $id): MailMessage;

    /**
     * @return list<array{id: string, name: string, user: bool}>
     */
    public function labels(): array;

    public function createLabel(string $name): string;

    /**
     * @return string the label's id afterwards: a provider that can't rename
     *                in place (Outlook) creates a new label
     */
    public function renameLabel(string $id, string $name): string;

    /**
     * Whether a label on a message is one the owner made (not a system label).
     */
    public function isOwnersLabel(string $id): bool;

    /**
     * @param  list<string>  $add
     * @param  list<string>  $remove
     */
    public function modify(string $messageId, array $add, array $remove): void;

    /**
     * Whether anyone but the owner wrote in the thread after `$after`.
     */
    public function threadHasReplyAfter(string $threadId, CarbonInterface $after): bool;

    public function hasSentTo(string $address): bool;

    /**
     * @return list<string> bodies of the owner's latest emails to `$address`
     */
    public function recentRepliesTo(string $address, int $limit): array;

    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     */
    public function saveDraft(?string $draftId, MailMessage $original, array $to, array $cc, string $body): string;

    /**
     * The draft's current text, or null if the owner sent or deleted it.
     */
    public function draftBody(string $draftId): ?string;
}
