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
 * Smart Inbox's Outlook access through Microsoft Graph. Outlook has
 * categories instead of labels: a category is named on the message, so its
 * name is its id. Archiving moves the message to the Archive folder. Like
 * Gmail, it has no way to send mail: drafts only.
 */
class OutlookMailbox implements Mailbox
{
    private const string BASE_URL = 'https://graph.microsoft.com/v1.0/me';

    private const int MAX_BODY_CHARS = 6000;

    private const string ARCHIVE_FOLDER = 'archive';

    private const string MESSAGE_FIELDS = 'id,conversationId,subject,from,replyTo,toRecipients,ccRecipients,receivedDateTime,internetMessageId,body,bodyPreview,categories,internetMessageHeaders,isDraft';

    private ?string $ownAddress = null;

    public function __construct(private readonly ConnectorCredential $credential) {}

    public function ownAddress(): string
    {
        if ($this->ownAddress === null) {
            $me = $this->get('', ['$select' => 'mail,userPrincipalName']);
            $this->ownAddress = Str::lower((string) ($me['mail'] ?? $me['userPrincipalName']));
        }

        return $this->ownAddress;
    }

    public function newMessageIds(CarbonInterface $since, int $limit): array
    {
        $result = $this->get('/mailFolders/inbox/messages', [
            '$filter' => 'receivedDateTime ge '.$since->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            '$orderby' => 'receivedDateTime desc',
            '$top' => $limit,
            '$select' => 'id',
        ]);

        return collect($result['value'] ?? [])->pluck('id')->map(fn ($id): string => (string) $id)->values()->all();
    }

    /**
     * Ids of messages in any folder matching a search.
     *
     * @return list<string>
     */
    public function searchIds(string $query, int $limit): array
    {
        return collect($this->get('/messages', ['$search' => '"'.str_replace('"', '', $query).'"', '$top' => $limit, '$select' => 'id'])['value'] ?? [])
            ->pluck('id')->map(fn ($id): string => (string) $id)->values()->all();
    }

    /**
     * The newest messages in one mail folder.
     *
     * @return list<string>
     */
    public function folderMessageIds(string $folderId, int $limit): array
    {
        return collect($this->get('/mailFolders/'.rawurlencode($folderId).'/messages', ['$top' => $limit, '$orderby' => 'receivedDateTime desc', '$select' => 'id'])['value'] ?? [])
            ->pluck('id')->map(fn ($id): string => (string) $id)->values()->all();
    }

    /**
     * Top-level mail folders, for choosing one.
     *
     * @return list<array{id: string, name: string, count: int}>
     */
    public function folders(): array
    {
        return collect($this->get('/mailFolders', ['$top' => 100, '$select' => 'id,displayName,totalItemCount'])['value'] ?? [])
            ->map(fn (array $folder): array => ['id' => (string) $folder['id'], 'name' => (string) $folder['displayName'], 'count' => (int) ($folder['totalItemCount'] ?? 0)])
            ->values()
            ->all();
    }

    public function message(string $id): MailMessage
    {
        $message = $this->get('/messages/'.rawurlencode($id), ['$select' => self::MESSAGE_FIELDS]);
        $headers = collect($message['internetMessageHeaders'] ?? [])
            ->mapWithKeys(fn (array $header): array => [Str::lower($header['name'] ?? '') => (string) ($header['value'] ?? '')]);

        return new MailMessage(
            id: (string) $message['id'],
            threadId: (string) ($message['conversationId'] ?? $message['id']),
            from: $this->address($message['from'] ?? null),
            replyTo: collect($message['replyTo'] ?? [])->map(fn (array $recipient): string => $this->address($recipient))->first(),
            to: collect($message['toRecipients'] ?? [])->map(fn (array $recipient): string => $this->address($recipient))->values()->all(),
            cc: collect($message['ccRecipients'] ?? [])->map(fn (array $recipient): string => $this->address($recipient))->values()->all(),
            subject: (string) ($message['subject'] ?? ''),
            receivedAt: isset($message['receivedDateTime']) ? now()->parse($message['receivedDateTime']) : null,
            messageIdHeader: $message['internetMessageId'] ?? null,
            body: Str::limit(trim((string) ($message['body']['content'] ?? '')) ?: (string) ($message['bodyPreview'] ?? ''), self::MAX_BODY_CHARS),
            snippet: (string) ($message['bodyPreview'] ?? ''),
            labelIds: ['INBOX', ...array_values(array_map('strval', $message['categories'] ?? []))],
            isBulk: $headers->has('list-unsubscribe')
                || Str::lower($headers->get('precedence', '')) === 'bulk'
                || ($headers->has('auto-submitted') && Str::lower($headers->get('auto-submitted')) !== 'no'),
        );
    }

    public function labels(): array
    {
        return collect($this->get('/outlook/masterCategories')['value'] ?? [])
            ->map(fn (array $category): array => ['id' => (string) $category['displayName'], 'name' => (string) $category['displayName'], 'user' => true])
            ->values()
            ->all();
    }

    public function createLabel(string $name): string
    {
        return (string) $this->send('post', '/outlook/masterCategories', ['displayName' => $name, 'color' => 'preset7'])['displayName'];
    }

    /**
     * Outlook can't rename a category, so the new name becomes a new one;
     * mail already filed keeps the old category.
     */
    public function renameLabel(string $id, string $name): string
    {
        $exists = collect($this->labels())->contains(fn (array $label): bool => Str::lower($label['name']) === Str::lower($name));

        return $exists ? $name : $this->createLabel($name);
    }

    public function isOwnersLabel(string $id): bool
    {
        return $id !== 'INBOX';
    }

    public function modify(string $messageId, array $add, array $remove): void
    {
        $categoriesToAdd = array_values(array_diff($add, ['INBOX']));
        $categoriesToRemove = array_values(array_diff($remove, ['INBOX']));
        $path = '/messages/'.rawurlencode($messageId);

        if ($categoriesToAdd !== [] || $categoriesToRemove !== []) {
            $current = $this->get($path, ['$select' => 'categories'])['categories'] ?? [];
            $categories = array_values(array_unique(array_diff([...$current, ...$categoriesToAdd], $categoriesToRemove)));

            $this->send('patch', $path, ['categories' => $categories]);
        }

        if (in_array('INBOX', $remove, true)) {
            $this->send('post', "{$path}/move", ['destinationId' => self::ARCHIVE_FOLDER]);
        }
    }

    public function threadHasReplyAfter(string $threadId, CarbonInterface $after): bool
    {
        $own = $this->ownAddress();

        $messages = $this->get('/messages', [
            '$filter' => "conversationId eq '".str_replace("'", "''", $threadId)."'",
            '$select' => 'from,receivedDateTime',
            '$top' => 50,
        ]);

        return collect($messages['value'] ?? [])->contains(function (array $message) use ($after, $own): bool {
            $receivedAt = isset($message['receivedDateTime']) ? now()->parse($message['receivedDateTime']) : null;

            return $receivedAt?->gt($after) === true && MailAddress::bare($this->address($message['from'] ?? null)) !== $own;
        });
    }

    public function hasSentTo(string $address): bool
    {
        return $this->sentTo($address, 1) !== [];
    }

    public function recentRepliesTo(string $address, int $limit): array
    {
        return collect($this->sentTo($address, $limit))
            ->map(fn (array $message): string => Str::limit(trim((string) ($message['body']['content'] ?? '')), 1500))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * A reply-all draft in the original thread, then its recipients and text
     * set to Smart Inbox's choice. Updating an existing draft rewrites it.
     */
    public function saveDraft(?string $draftId, MailMessage $original, array $to, array $cc, string $body): string
    {
        $draftId ??= (string) $this->send('post', '/messages/'.rawurlencode($original->id).'/createReplyAll', [])['id'];

        $this->send('patch', '/messages/'.rawurlencode($draftId), [
            'body' => ['contentType' => 'Text', 'content' => $body],
            'toRecipients' => $this->recipients($to),
            'ccRecipients' => $this->recipients($cc),
        ]);

        return $draftId;
    }

    public function draftBody(string $draftId): ?string
    {
        $response = $this->request()->get(self::BASE_URL.'/messages/'.rawurlencode($draftId), ['$select' => 'body,isDraft']);

        if ($response->status() === 404) {
            return null;
        }

        $message = $this->json($response, '/messages');

        return ($message['isDraft'] ?? true) ? trim((string) ($message['body']['content'] ?? '')) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sentTo(string $address, int $limit): array
    {
        return array_values($this->get('/mailFolders/sentitems/messages', [
            '$search' => '"to:'.str_replace('"', '', MailAddress::bare($address)).'"',
            '$top' => $limit,
            '$select' => 'id,body',
        ])['value'] ?? []);
    }

    /**
     * @param  array{emailAddress?: array{name?: string, address?: string}}|null  $recipient
     */
    private function address(?array $recipient): string
    {
        $email = (string) ($recipient['emailAddress']['address'] ?? '');
        $name = trim((string) ($recipient['emailAddress']['name'] ?? ''));

        return $name !== '' && $name !== $email ? "{$name} <{$email}>" : $email;
    }

    /**
     * @param  list<string>  $addresses
     * @return list<array{emailAddress: array{address: string}}>
     */
    private function recipients(array $addresses): array
    {
        return array_map(fn (string $address): array => ['emailAddress' => ['address' => MailAddress::bare($address)]], array_values($addresses));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $endpoint, array $query = []): array
    {
        return $this->json($this->request()->get(self::BASE_URL.$endpoint, $query), $endpoint ?: '/me');
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

        return Http::withToken($token)
            ->withHeaders(['Prefer' => 'outlook.body-content-type="text"'])
            ->timeout(30);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response, string $endpoint): array
    {
        if ($response->failed()) {
            throw new RuntimeException("Outlook API error [{$endpoint}]: ".($response->json('error.message') ?? $response->body()));
        }

        return $response->json() ?? [];
    }
}
