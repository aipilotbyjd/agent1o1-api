<?php

namespace App\Nodes\Integrations\Email;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use App\Services\Connectors\SmtpMailer;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Shared message building for the Email (SMTP) nodes. The "account" is a mail
 * server the member connected (host, port, login, From address): it stores
 * several fields, so these nodes read them whole (`resolveCredentialData`)
 * instead of one token.
 *
 * Everything a person types is checked before it reaches the server: an
 * address that isn't one, a header with a line break in it, or an attachment
 * that is too big fails the step with a sentence saying which.
 */
abstract class AbstractEmailNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    /**
     * The most one message may carry in attachments (most servers refuse more).
     */
    protected const int MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    protected const int MAX_ATTACHMENTS = 10;

    public function category(): string
    {
        return 'smtp';
    }

    protected function mailer(): SmtpMailer
    {
        return app(SmtpMailer::class);
    }

    /**
     * Addresses from a comma-separated field (or a list), each checked.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException when one isn't an email address
     */
    protected function addresses(mixed $value, string $label): array
    {
        $parts = is_array($value) ? $value : preg_split('/[,;\n]+/', (string) $value);
        $addresses = [];

        foreach ((array) $parts as $part) {
            $address = trim(is_array($part) ? (string) ($part['email'] ?? '') : (string) $part);

            if ($address === '') {
                continue;
            }

            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException("\"{$address}\" in {$label} is not an email address.");
            }

            $addresses[] = $address;
        }

        return array_values(array_unique($addresses));
    }

    /**
     * A header value (subject, name): one line, no control characters.
     */
    protected function oneLine(mixed $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string) $value));
    }

    /**
     * Build the message the Send nodes share: sender, subject, body and attachments.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $account
     * @param  list<string>  $to
     *
     * @throws InvalidArgumentException when something is missing or malformed
     */
    protected function buildMessage(array $config, array $account, array $to): Email
    {
        $subject = $this->oneLine($config['subject'] ?? '');
        $body = (string) ($config['body'] ?? '');

        if ($subject === '') {
            throw new InvalidArgumentException('The subject is required.');
        }

        if (trim($body) === '') {
            throw new InvalidArgumentException('The message body is required.');
        }

        $sender = $this->mailer()->sender($account);
        $fromName = $this->oneLine($config['from_name'] ?? '') ?: $sender['name'];

        $message = (new Email)
            ->from(new Address($sender['address'], $fromName))
            ->to(...$to)
            ->subject($subject);

        if (($cc = $this->addresses($config['cc'] ?? null, 'Cc')) !== []) {
            $message->cc(...$cc);
        }

        if (($bcc = $this->addresses($config['bcc'] ?? null, 'Bcc')) !== []) {
            $message->bcc(...$bcc);
        }

        if (($replyTo = $this->addresses($config['reply_to'] ?? null, 'Reply-To')) !== []) {
            $message->replyTo(...$replyTo);
        }

        // HTML gets a plain-text twin so clients that don't show HTML still read something.
        if (filter_var($config['is_html'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $message->html($body)->text(trim(html_entity_decode(strip_tags((string) preg_replace('#<(br|/p|/div|/li|/h[1-6])[^>]*>#i', "\n", $body)))));
        } else {
            $message->text($body);
        }

        $message->getHeaders()->addIdHeader('Message-ID', bin2hex(random_bytes(16)).'@'.(substr(strrchr($sender['address'], '@') ?: '@localhost', 1)));

        $this->attach($message, $config['attachments'] ?? null);

        return $message;
    }

    /**
     * Fetch each attachment (a link, or `{url, filename}`) and add it.
     *
     * @throws InvalidArgumentException when a file can't be fetched or is too big
     */
    private function attach(Email $message, mixed $attachments): void
    {
        $items = is_array($attachments) ? $attachments : preg_split('/[\n,]+/', (string) $attachments);
        $items = array_values(array_filter((array) $items, fn (mixed $item): bool => is_array($item) || trim((string) $item) !== ''));

        if (count($items) > self::MAX_ATTACHMENTS) {
            throw new InvalidArgumentException('At most '.self::MAX_ATTACHMENTS.' attachments can be sent in one email.');
        }

        $total = 0;

        foreach ($items as $item) {
            $url = trim((string) (is_array($item) ? ($item['url'] ?? '') : $item));
            $name = is_array($item) ? trim((string) ($item['filename'] ?? $item['name'] ?? '')) : '';

            if (! preg_match('#^https?://#i', $url)) {
                throw new InvalidArgumentException("Attachment \"{$url}\" must be a link starting with http:// or https://.");
            }

            $response = Http::timeout(30)->get($url);

            if ($response->failed()) {
                throw new InvalidArgumentException("Could not download the attachment {$url} (HTTP {$response->status()}).");
            }

            $content = $response->body();
            $total += strlen($content);

            if ($total > self::MAX_ATTACHMENT_BYTES) {
                throw new InvalidArgumentException('The attachments are over the '.(self::MAX_ATTACHMENT_BYTES / 1024 / 1024).' MB limit.');
            }

            $name = $this->oneLine($name !== '' ? $name : (basename((string) parse_url($url, PHP_URL_PATH)) ?: 'attachment'));
            $type = explode(';', (string) $response->header('Content-Type'))[0] ?: 'application/octet-stream';

            $message->attach($content, $name, $type);
        }
    }

    /**
     * Send through the account's server, turning a refusal into a sentence.
     *
     * @param  array<string, mixed>  $account
     *
     * @throws RuntimeException
     */
    protected function deliver(array $account, Email $message): void
    {
        try {
            $this->mailer()->send($account, $message);
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException('The mail server did not send the message: '.mb_substr($e->getMessage(), 0, 300));
        }
    }

    /**
     * The id the message went out with, without the angle brackets.
     */
    protected function messageId(Email $message): string
    {
        return trim((string) $message->getHeaders()->get('Message-ID')?->getBodyAsString(), '<>');
    }

    /**
     * The settings every Send node shares for body and extras.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function messageFields(): array
    {
        return [
            'subject' => Field::text('Subject', null, 'Your order has shipped'),
            'body' => Field::textarea('Message', 'The text of the email. Tick "Message is HTML" to send formatting.'),
            'is_html' => Field::boolean('Message is HTML', 'The message is HTML. A plain-text version is added for you.'),
            'cc' => Field::advanced(Field::emails('Cc')),
            'bcc' => Field::advanced(Field::emails('Bcc')),
            'reply_to' => Field::advanced(Field::emails('Reply-To', 'Where replies go, if not the From address.')),
            'from_name' => Field::advanced(Field::text('From name', 'Shown as the sender. Defaults to the name on the connection.')),
            'attachments' => Field::advanced(Field::list('Attachments', 'Links to files to attach (up to '.self::MAX_ATTACHMENTS.', '.(self::MAX_ATTACHMENT_BYTES / 1024 / 1024).' MB in all).', 'https://example.com/invoice.pdf')),
        ];
    }
}
