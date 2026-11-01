<?php

namespace App\Mail\Assistant;

use App\Services\Assistant\Branding\Brand;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

/**
 * The assistant's reply to an email, threaded under the owner's message
 * and sent from the assistant's own address.
 */
class AssistantEmailReply extends Mailable
{
    use Queueable;

    public function __construct(
        public Brand $brand,
        public string $originalSubject,
        public string $body,
        public ?string $inReplyTo,
        public ?string $references,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->originalSubject === '' ? $this->brand->name : $this->originalSubject;

        return new Envelope(
            from: new Address($this->brand->email(), $this->brand->name),
            subject: Str::startsWith(Str::lower($subject), 're:') ? $subject : "Re: {$subject}",
            using: [function (Email $message): void {
                if (filled($this->inReplyTo)) {
                    $message->getHeaders()->addIdHeader('In-Reply-To', trim($this->inReplyTo, '<>'));
                }
            }],
        );
    }

    public function headers(): Headers
    {
        $references = array_values(array_filter(preg_split('/\s+/', (string) $this->references) ?: []));

        return new Headers(
            references: array_map(fn (string $id): string => trim($id, '<>'), $references),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.assistant.reply-text', with: ['body' => $this->body, 'brand' => $this->brand]);
    }
}
