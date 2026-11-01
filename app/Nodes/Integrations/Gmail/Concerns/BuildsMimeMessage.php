<?php

namespace App\Nodes\Integrations\Gmail\Concerns;

use InvalidArgumentException;

/**
 * Builds the raw RFC 5322 message Gmail's send and draft endpoints take.
 *
 * Headers are cleaned of line breaks (so a value can't add headers of its
 * own), a non-ASCII subject is encoded as the standard requires, the body
 * is base64 so any text survives, and attachments make it multipart.
 */
trait BuildsMimeMessage
{
    /**
     * @param  array<string, string|list<string>>  $headers  name => value; an address list may be a list
     * @param  list<array<string, mixed>>  $attachments  each `filename` plus `content` (text) or `content_base64`, and optionally `mime_type`
     * @return string the message, base64url encoded
     *
     * @throws InvalidArgumentException when an attachment has no name or content
     */
    protected function rawMessage(array $headers, string $body, bool $isHtml = false, array $attachments = []): string
    {
        $lines = ['MIME-Version: 1.0'];

        foreach ($headers as $name => $value) {
            $value = is_array($value) ? implode(', ', $value) : $value;

            if (trim($value) !== '') {
                $lines[] = $name.': '.($name === 'Subject' ? $this->encodedSubject($value) : $this->headerValue($value));
            }
        }

        $bodyPart = [
            'Content-Type: '.($isHtml ? 'text/html' : 'text/plain').'; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            rtrim(chunk_split(base64_encode($body), 76, "\r\n")),
        ];

        if ($attachments === []) {
            $message = implode("\r\n", [...$lines, ...$bodyPart]);
        } else {
            $boundary = 'mix_'.bin2hex(random_bytes(12));
            $parts = ["--{$boundary}", ...$bodyPart];

            foreach ($attachments as $attachment) {
                array_push($parts, "--{$boundary}", ...$this->attachmentPart($attachment));
            }

            $message = implode("\r\n", [
                ...$lines,
                "Content-Type: multipart/mixed; boundary=\"{$boundary}\"",
                '',
                ...$parts,
                "--{$boundary}--",
            ]);
        }

        return rtrim(strtr(base64_encode($message), '+/', '-_'), '=');
    }

    /**
     * One attachment's MIME headers, a blank line and its base64 content.
     *
     * @param  array<string, mixed>  $attachment
     * @return list<string>
     */
    private function attachmentPart(array $attachment): array
    {
        $filename = $this->headerValue((string) ($attachment['filename'] ?? ''));
        $encoded = isset($attachment['content_base64']) && $attachment['content_base64'] !== ''
            ? preg_replace('/\s+/', '', (string) $attachment['content_base64'])
            : (isset($attachment['content']) ? base64_encode((string) $attachment['content']) : '');

        if ($filename === '' || $encoded === '' || $encoded === null) {
            throw new InvalidArgumentException('Gmail: every attachment needs a filename and its content.');
        }

        $mimeType = $this->headerValue((string) ($attachment['mime_type'] ?? '')) ?: 'application/octet-stream';
        $quoted = str_replace(['\\', '"'], ['\\\\', '\\"'], $filename);

        return [
            "Content-Type: {$mimeType}; name=\"{$quoted}\"",
            "Content-Disposition: attachment; filename=\"{$quoted}\"",
            'Content-Transfer-Encoding: base64',
            '',
            rtrim(chunk_split($encoded, 76, "\r\n")),
        ];
    }

    /**
     * A header value on one line.
     */
    private function headerValue(string $value): string
    {
        return trim((string) preg_replace('/[\r\n]+/', ' ', $value));
    }

    /**
     * A subject, encoded when it holds anything but plain ASCII.
     */
    private function encodedSubject(string $subject): string
    {
        $subject = $this->headerValue($subject);

        return preg_match('/^[\x20-\x7E]*$/', $subject) === 1
            ? $subject
            : mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
    }

    /**
     * Addresses from a comma, semicolon or whitespace separated string.
     *
     * @return list<string>
     */
    protected function addressList(mixed $value): array
    {
        $parts = is_array($value) ? $value : preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $address): string => $this->headerValue((string) $address),
            (array) $parts,
        ))));
    }
}
