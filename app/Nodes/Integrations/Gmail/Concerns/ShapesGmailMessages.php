<?php

namespace App\Nodes\Integrations\Gmail\Concerns;

/**
 * Turns a Gmail message in its full form into the flat record a flow can use:
 * the headers people care about, the text of the body and the attachments.
 */
trait ShapesGmailMessages
{
    /**
     * The longest body kept, in characters. Mail can be megabytes of HTML.
     */
    private const int MAX_BODY_CHARS = 100_000;

    /**
     * @param  array<string, mixed>  $message  a `format=full` message
     * @return array<string, mixed>
     */
    protected function flatMessage(array $message): array
    {
        $headers = collect($message['payload']['headers'] ?? [])
            ->mapWithKeys(fn (array $header): array => [strtolower((string) ($header['name'] ?? '')) => $header['value'] ?? null]);

        $text = '';
        $html = '';
        $attachments = [];
        $this->walkParts((array) ($message['payload'] ?? []), $text, $html, $attachments);

        $labels = (array) ($message['labelIds'] ?? []);

        return [
            'id' => $message['id'] ?? null,
            'thread_id' => $message['threadId'] ?? null,
            'message_id' => $headers->get('message-id'),
            'from' => $headers->get('from'),
            'to' => $headers->get('to'),
            'cc' => $headers->get('cc'),
            'reply_to' => $headers->get('reply-to'),
            'subject' => $headers->get('subject'),
            'date' => $headers->get('date'),
            'snippet' => $message['snippet'] ?? null,
            'body_text' => mb_substr($text !== '' ? $text : trim(html_entity_decode(strip_tags((string) preg_replace('~<(script|style)\b.*?</\1>~is', '', $html)))), 0, self::MAX_BODY_CHARS),
            'body_html' => mb_substr($html, 0, self::MAX_BODY_CHARS),
            'attachments' => $attachments,
            'has_attachments' => $attachments !== [],
            'label_ids' => $labels,
            'is_unread' => in_array('UNREAD', $labels, true),
        ];
    }

    /**
     * Collect the text parts and attachments of a message part and its children.
     *
     * @param  array<string, mixed>  $part
     * @param  list<array<string, mixed>>  $attachments
     */
    private function walkParts(array $part, string &$text, string &$html, array &$attachments): void
    {
        $mimeType = (string) ($part['mimeType'] ?? '');
        $body = (array) ($part['body'] ?? []);

        if (($part['filename'] ?? '') !== '' && isset($body['attachmentId'])) {
            $attachments[] = [
                'attachment_id' => $body['attachmentId'],
                'filename' => $part['filename'],
                'mime_type' => $mimeType,
                'size' => (int) ($body['size'] ?? 0),
            ];
        } elseif (isset($body['data']) && $body['data'] !== '') {
            $decoded = (string) base64_decode(strtr((string) $body['data'], '-_', '+/'), false);

            if ($mimeType === 'text/plain' && $text === '') {
                $text = $decoded;
            } elseif ($mimeType === 'text/html' && $html === '') {
                $html = $decoded;
            }
        }

        foreach ((array) ($part['parts'] ?? []) as $child) {
            if (is_array($child)) {
                $this->walkParts($child, $text, $html, $attachments);
            }
        }
    }
}
