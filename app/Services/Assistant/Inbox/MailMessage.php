<?php

namespace App\Services\Assistant\Inbox;

use Carbon\CarbonInterface;

/**
 * One email as Smart Inbox needs it, the same for every mail provider.
 */
final readonly class MailMessage
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<string>  $labelIds  the provider's label ids on the message
     */
    public function __construct(
        public string $id,
        public string $threadId,
        public string $from,
        public ?string $replyTo,
        public array $to,
        public array $cc,
        public string $subject,
        public ?CarbonInterface $receivedAt,
        public ?string $messageIdHeader,
        public string $body,
        public string $snippet,
        public array $labelIds,
        public bool $isBulk,
    ) {}

    /**
     * The bare address of the sender, e.g. "Sam <sam@x.test>" → "sam@x.test".
     */
    public function fromAddress(): string
    {
        return MailAddress::bare($this->from);
    }
}
