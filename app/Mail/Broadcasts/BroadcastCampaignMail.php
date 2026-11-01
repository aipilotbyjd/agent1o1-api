<?php

namespace App\Mail\Broadcasts;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;

/**
 * One copy of an email campaign. The admin writes Markdown; raw HTML in it is
 * stripped and unsafe links dropped, so the body can't carry markup of its own.
 * The placeholders `{{ name }}`, `{{ first_name }}` and `{{ email }}` are
 * filled in per recipient (HTML-escaped first).
 *
 * Marketing mail carries `unsubscribeUrl` (a signed page) and `oneClickUrl`
 * (the signed endpoint mail clients POST to for RFC 8058 one-click unsubscribe).
 * Sent by `SendBroadcastEmailJob`, which queues it, so this is not queued itself.
 */
class BroadcastCampaignMail extends Mailable
{
    public function __construct(
        public string $campaignSubject,
        public string $bodyMarkdown,
        public string $recipientName,
        public string $recipientEmail,
        public ?string $unsubscribeUrl = null,
        public ?string $oneClickUrl = null,
        public bool $isTest = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->isTest ? '[TEST] ' : '').$this->campaignSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.broadcasts.campaign',
            text: 'mail.broadcasts.campaign-text',
            with: ['bodyHtml' => $this->bodyHtml(), 'bodyText' => $this->bodyText()],
        );
    }

    public function headers(): Headers
    {
        if ($this->oneClickUrl === null) {
            return new Headers;
        }

        return new Headers(text: [
            'List-Unsubscribe' => "<{$this->oneClickUrl}>",
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function bodyHtml(): string
    {
        return Str::markdown($this->withPlaceholders($this->bodyMarkdown), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function bodyText(): string
    {
        return trim(strip_tags($this->bodyHtml()));
    }

    private function withPlaceholders(string $markdown): string
    {
        $firstName = Str::before(trim($this->recipientName), ' ');

        return strtr(preg_replace('/\{\{\s*(name|first_name|email)\s*\}\}/', '{{$1}}', $markdown) ?? $markdown, [
            '{{name}}' => e($this->recipientName),
            '{{first_name}}' => e($firstName === '' ? $this->recipientName : $firstName),
            '{{email}}' => e($this->recipientEmail),
        ]);
    }
}
