<?php

namespace App\Services\Assistant\Inbox;

use App\Models\Connectors\ConnectorCredential;
use App\Services\Connectors\ConnectorTokens;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Smart Inbox's Gmail access, through the owner's own connected account.
 * Labels, drafts and inbox moves only: it has no way to send mail.
 */
class GmailMailbox implements Mailbox
{
    private const string BASE_URL = 'https://gmail.googleapis.com/gmail/v1/users/me';

    private const int MAX_BODY_CHARS = 6000;

    private ?string $ownAddress = null;

    public function __construct(private readonly ConnectorCredential $credential) {}

    public function ownAddress(): string
    {
        return $this->ownAddress ??= Str::lower((string) $this->get('/profile')['emailAddress']);
    }

    public function newMessageIds(CarbonInterface $since, int $limit): array
    {
        $result = $this->get('/messages', ['q' => "in:inbox after:{$since->getTimestamp()}", 'maxResults' => $limit]);

        return collect($result['messages'] ?? [])->pluck('id')->map(fn ($id): string => (string) $id)->values()->all();
    }

    /**
     * Ids of messages matching a Gmail search, newest first.
     *
     * @return list<string>
     */
    public function searchIds(string $query, int $limit): array
    {
        return collect($this->get('/messages', ['q' => $query, 'maxResults' => $limit])['messages'] ?? [])
            ->pluck('id')->map(fn ($id): string => (string) $id)->values()->all();
    }

    public function message(string $id): MailMessage
    {
        $message = $this->get("/messages/{$id}", ['format' => 'full']);
        $headers = $this->headers($message['payload']['headers'] ?? []);

        return new MailMessage(
            id: (string) $message['id'],
            threadId: (string) ($message['threadId'] ?? $message['id']),
            from: (string) ($headers['from'] ?? ''),
            replyTo: $headers['reply-to'] ?? null,
            to: MailAddress::list($headers['to'] ?? null),
            cc: MailAddress::list($headers['cc'] ?? null),
            subject: (string) ($headers['subject'] ?? ''),
            receivedAt: isset($message['internalDate']) ? now()->setTimestamp((int) ($message['internalDate'] / 1000)) : null,
            messageIdHeader: $headers['message-id'] ?? null,
            body: Str::limit($this->bodyText($message['payload'] ?? []) ?: html_entity_decode((string) ($message['snippet'] ?? ''), ENT_QUOTES), self::MAX_BODY_CHARS),
            snippet: html_entity_decode((string) ($message['snippet'] ?? ''), ENT_QUOTES),
            labelIds: array_values(array_map('strval', $message['labelIds'] ?? [])),
            isBulk: isset($headers['list-unsubscribe'])
                || Str::lower($headers['precedence'] ?? '') === 'bulk'
                || (isset($headers['auto-submitted']) && Str::lower($headers['auto-submitted']) !== 'no'),
        );
    }

    public function labels(): array
    {
        return collect($this->get('/labels')['labels'] ?? [])
            ->map(fn (array $label): array => ['id' => (string) $label['id'], 'name' => (string) $label['name'], 'user' => ($label['type'] ?? '') === 'user'])
            ->values()
            ->all();
    }

    public function createLabel(string $name): string
    {
        return (string) $this->send('post', '/labels', [
            'name' => $name,
            'labelListVisibility' => 'labelShow',
            'messageListVisibility' => 'show',
        ])['id'];
    }

    public function renameLabel(string $id, string $name): string
    {
        $this->send('patch', "/labels/{$id}", ['name' => $name]);

        return $id;
    }

    public function isOwnersLabel(string $id): bool
    {
        return Str::startsWith($id, 'Label_');
    }

    public function modify(string $messageId, array $add, array $remove): void
    {
        if ($add === [] && $remove === []) {
            return;
        }

        $this->send('post', "/messages/{$messageId}/modify", ['addLabelIds' => array_values($add), 'removeLabelIds' => array_values($remove)]);
    }

    public function threadHasReplyAfter(string $threadId, CarbonInterface $after): bool
    {
        $thread = $this->get('/threads/'.rawurlencode($threadId), ['format' => 'metadata', 'metadataHeaders' => 'From']);
        $own = $this->ownAddress();

        return collect($thread['messages'] ?? [])->contains(function (array $message) use ($after, $own): bool {
            $sentAt = (int) ($message['internalDate'] ?? 0) / 1000;
            $from = MailAddress::bare($this->headers($message['payload']['headers'] ?? [])['from'] ?? '');

            return $sentAt > $after->getTimestamp() && Str::lower($from) !== $own;
        });
    }

    public function hasSentTo(string $address): bool
    {
        return ($this->get('/messages', ['q' => 'in:sent to:'.MailAddress::bare($address), 'maxResults' => 1])['messages'] ?? []) !== [];
    }

    public function recentRepliesTo(string $address, int $limit): array
    {
        return collect($this->get('/messages', ['q' => 'in:sent to:'.MailAddress::bare($address), 'maxResults' => $limit])['messages'] ?? [])
            ->map(fn (array $listed): string => Str::limit($this->bodyText($this->get("/messages/{$listed['id']}", ['format' => 'full'])['payload'] ?? []), 1500))
            ->filter()
            ->values()
            ->all();
    }

    public function saveDraft(?string $draftId, MailMessage $original, array $to, array $cc, string $body): string
    {
        $subject = Str::startsWith(Str::lower($original->subject), 're:') ? $original->subject : 'Re: '.$original->subject;

        $headers = array_filter([
            'To' => implode(', ', $to),
            'Cc' => $cc === [] ? null : implode(', ', $cc),
            'Subject' => $subject,
            'In-Reply-To' => $original->messageIdHeader,
            'References' => $original->messageIdHeader,
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);

        $raw = collect($headers)->map(fn (string $value, string $name): string => "{$name}: {$value}")->implode("\r\n")."\r\n\r\n".$body;
        $message = ['raw' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '='), 'threadId' => $original->threadId];

        $result = $draftId === null
            ? $this->send('post', '/drafts', ['message' => $message])
            : $this->send('put', "/drafts/{$draftId}", ['id' => $draftId, 'message' => $message]);

        return (string) $result['id'];
    }

    public function draftBody(string $draftId): ?string
    {
        $response = $this->request()->get(self::BASE_URL."/drafts/{$draftId}", ['format' => 'full']);

        if ($response->status() === 404) {
            return null;
        }

        return $this->bodyText($this->json($response, "/drafts/{$draftId}")['message']['payload'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $endpoint, array $query = []): array
    {
        return $this->json($this->request()->get(self::BASE_URL.$endpoint, $query), $endpoint);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function send(string $method, string $endpoint, array $body): array
    {
        return $this->json($this->request()->asJson()->{$method}(self::BASE_URL.$endpoint, $body), $endpoint);
    }

    private function request(): PendingRequest
    {
        $token = app(ConnectorTokens::class)->accessToken($this->credential);

        return Http::withToken($token)->timeout(30);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response, string $endpoint): array
    {
        if ($response->failed()) {
            throw new RuntimeException("Gmail API error [{$endpoint}]: ".($response->json('error.message') ?? $response->body()));
        }

        return $response->json() ?? [];
    }

    /**
     * @param  list<array{name?: string, value?: string}>  $headers
     * @return array<string, string>
     */
    private function headers(array $headers): array
    {
        return collect($headers)->mapWithKeys(fn (array $header): array => [Str::lower($header['name'] ?? '') => (string) ($header['value'] ?? '')])->all();
    }

    /**
     * The message's plain text: its text/plain part, else its HTML with the
     * tags stripped.
     *
     * @param  array<string, mixed>  $payload
     */
    private function bodyText(array $payload): string
    {
        $plain = $this->findPart($payload, 'text/plain');

        if ($plain !== null) {
            return trim($plain);
        }

        $html = $this->findPart($payload, 'text/html');

        return $html === null ? '' : trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES)) ?? '');
    }

    /**
     * @param  array<string, mixed>  $part
     */
    private function findPart(array $part, string $mimeType): ?string
    {
        if (($part['mimeType'] ?? '') === $mimeType && isset($part['body']['data'])) {
            return base64_decode(strtr((string) $part['body']['data'], '-_', '+/')) ?: null;
        }

        foreach ($part['parts'] ?? [] as $child) {
            $found = $this->findPart($child, $mimeType);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
