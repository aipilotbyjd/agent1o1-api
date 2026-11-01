<?php

namespace App\Services\Assistant\Channels;

use App\Enums\Assistant\AssistantSessionOrigin;
use App\Mail\Assistant\AssistantEmailReply;
use App\Models\Assistant\AssistantSession;
use App\Services\Assistant\Branding\BrandRepository;
use App\Services\Assistant\Inbox\MailAddress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Email to the assistant's address (delivered by an inbound mail provider
 * as JSON, Postmark's format). The sender must be a verified user and the
 * mail must pass SPF or DKIM; the reply goes back in the same thread. Mail
 * from strangers is dropped silently, so nobody can use the address to
 * bounce spam.
 */
class EmailChannel
{
    private const int MAX_BODY_CHARS = 20000;

    public function __construct(
        private readonly ChannelUsers $users,
        private readonly ChannelInbox $inbox,
        private readonly BrandRepository $brands,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return string what happened, for the provider's logs
     */
    public function handle(array $payload): string
    {
        $headers = $this->headers($payload['Headers'] ?? []);
        $brand = $this->brands->current();

        $addressed = collect([...($payload['ToFull'] ?? []), ...($payload['CcFull'] ?? [])])
            ->pluck('Email')
            ->contains(fn ($address): bool => is_string($address) && $brand->acceptsEmailTo($address));

        if (! $addressed) {
            return 'ignored: not addressed to the assistant';
        }

        if (config('assistant.channels.email.require_authentication') && ! $this->authenticated($headers)) {
            Log::info('Assistant email dropped: sender failed SPF and DKIM.', ['from' => $payload['FromFull']['Email'] ?? null]);

            return 'ignored: sender not authenticated';
        }

        $user = $this->users->byEmail((string) ($payload['FromFull']['Email'] ?? MailAddress::bare((string) ($payload['From'] ?? ''))));
        $assistant = $user === null ? null : $this->users->assistantFor($user);

        if ($assistant === null) {
            return 'ignored: unknown sender';
        }

        $messageId = (string) ($payload['MessageID'] ?? $headers['message-id'] ?? Str::uuid());
        $messageIdHeader = $headers['message-id'] ?? "<{$messageId}>";
        $root = $this->threadRoot($headers) ?? $messageIdHeader;

        $text = trim((string) ($payload['StrippedTextReply'] ?? '')) ?: trim((string) ($payload['TextBody'] ?? ''));
        $subject = trim((string) ($payload['Subject'] ?? ''));

        if ($text === '' && $subject === '') {
            return 'ignored: empty';
        }

        $this->inbox->receive(
            $assistant,
            AssistantSessionOrigin::Email,
            hash('sha256', $root),
            Str::limit(($subject !== '' ? "Subject: {$subject}\n\n" : '').$text, self::MAX_BODY_CHARS),
            [
                'reply_to' => (string) ($payload['FromFull']['Email'] ?? ''),
                'subject' => $subject,
                'in_reply_to' => $messageIdHeader,
                'references' => trim(($headers['references'] ?? '').' '.$messageIdHeader),
            ],
            title: $subject !== '' ? preg_replace('/^(re|fwd?):\s*/i', '', $subject) : null,
        );

        return 'accepted';
    }

    public function reply(AssistantSession $session, string $text): void
    {
        $context = $session->channel_context ?? [];

        if (blank($context['reply_to'] ?? null)) {
            return;
        }

        Mail::to($context['reply_to'])->send(new AssistantEmailReply(
            $this->brands->current($session->assistant->workspace),
            (string) ($context['subject'] ?? ''),
            $text,
            $context['in_reply_to'] ?? null,
            $context['references'] ?? null,
        ));
    }

    /**
     * Either check passing is enough: SPF proves the sending server, DKIM the
     * signing domain.
     *
     * @param  array<string, string>  $headers
     */
    private function authenticated(array $headers): bool
    {
        $results = Str::lower(($headers['authentication-results'] ?? '').' '.($headers['received-spf'] ?? ''));

        return Str::contains($results, ['dkim=pass', 'spf=pass']) || Str::startsWith(Str::lower($headers['received-spf'] ?? ''), 'pass');
    }

    /**
     * The first message of the thread, so every reply lands in one
     * conversation.
     *
     * @param  array<string, string>  $headers
     */
    private function threadRoot(array $headers): ?string
    {
        $references = preg_split('/\s+/', trim($headers['references'] ?? '')) ?: [];

        return collect($references)->filter()->first() ?? ($headers['in-reply-to'] ?? null);
    }

    /**
     * @param  list<array{Name?: string, Value?: string}>  $headers
     * @return array<string, string>
     */
    private function headers(array $headers): array
    {
        return Collection::make($headers)
            ->mapWithKeys(fn (array $header): array => [Str::lower($header['Name'] ?? '') => (string) ($header['Value'] ?? '')])
            ->all();
    }
}
