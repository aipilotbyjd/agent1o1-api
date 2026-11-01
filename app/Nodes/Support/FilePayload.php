<?php

namespace App\Nodes\Support;

use App\Services\Http\GuardedHttp;
use App\Services\Http\SsrfGuard;
use InvalidArgumentException;
use RuntimeException;

/**
 * How a step reads a file in and hands one on. A file is passed between steps
 * as `{filename, mime_type, size, content_base64}`: the shape the Gmail and
 * Slack steps already use for attachments, so a file one step makes can be
 * attached by another with `{{ nodes.step.file }}`.
 *
 * A step takes a file three ways, in this order: the object from an earlier
 * step (`file`), raw base64 (`file_base64`), or a link it should download
 * (`file_url`, fetched through the same outbound guard as Call API).
 */
final class FilePayload
{
    /**
     * The biggest file a step reads or makes.
     */
    public const int MAX_BYTES = 10 * 1024 * 1024;

    /**
     * The three ways of giving a file, as config fields.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function inputFields(string $label = 'File'): array
    {
        return [
            'file' => Field::json($label, 'A file from an earlier step, e.g. {{ nodes.fetch.file }}. Leave empty to use a link or base64 instead.', 'object'),
            'file_url' => Field::advanced([...Field::text("{$label} link", 'A public link to download the file from.', 'https://example.com/report.pdf'), 'format' => 'uri']),
            'file_base64' => Field::advanced(Field::textarea("{$label} (base64)", 'The file’s content, base64 encoded. Use when the file is not on a public link.')),
            'mime_type' => Field::advanced(Field::text('File type', 'Only needed when it cannot be worked out, e.g. application/pdf.', 'application/pdf')),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{content: string, mime_type: string, filename: string}
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function read(array $config): array
    {
        $given = is_array($config['file'] ?? null) ? $config['file'] : [];

        if (filled($given['content_base64'] ?? null)) {
            return self::fromBase64((string) $given['content_base64'], $given['mime_type'] ?? $config['mime_type'] ?? null, $given['filename'] ?? null);
        }

        if (filled($config['file_base64'] ?? null)) {
            return self::fromBase64((string) $config['file_base64'], $config['mime_type'] ?? null, null);
        }

        $url = trim((string) ($config['file_url'] ?? $given['url'] ?? ''));

        if ($url === '') {
            throw new InvalidArgumentException('Give the file as a file from an earlier step, a link or base64.');
        }

        $response = (new GuardedHttp(app(SsrfGuard::class)))->send('GET', $url, ['Accept' => '*/*'], timeoutSeconds: 60);

        if (! $response->successful()) {
            throw new RuntimeException("The file could not be downloaded (HTTP {$response->status()}).");
        }

        $body = $response->body();

        self::assertSize($body);

        $mime = trim((string) ($config['mime_type'] ?? '')) ?: self::sniff($body) ?: trim(explode(';', (string) $response->header('Content-Type'))[0]);

        return [
            'content' => $body,
            'mime_type' => $mime !== '' ? $mime : 'application/octet-stream',
            'filename' => basename((string) parse_url($url, PHP_URL_PATH)) ?: 'file',
        ];
    }

    /**
     * @return array{filename: string, mime_type: string, size: int, content_base64: string}
     *
     * @throws RuntimeException
     */
    public static function make(string $content, string $filename, string $mimeType): array
    {
        self::assertSize($content);

        return ['filename' => $filename, 'mime_type' => $mimeType, 'size' => strlen($content), 'content_base64' => base64_encode($content)];
    }

    /**
     * @return array{content: string, mime_type: string, filename: string}
     */
    private static function fromBase64(string $base64, mixed $mimeType, mixed $filename): array
    {
        $content = base64_decode(preg_replace('/^data:[^,]*,/', '', trim($base64)) ?? '', true);

        if ($content === false || $content === '') {
            throw new InvalidArgumentException('The file content is not valid base64.');
        }

        self::assertSize($content);

        return [
            'content' => $content,
            'mime_type' => trim((string) $mimeType) ?: self::sniff($content) ?: 'application/octet-stream',
            'filename' => trim((string) $filename) ?: 'file',
        ];
    }

    private static function assertSize(string $content): void
    {
        if (strlen($content) > self::MAX_BYTES) {
            throw new RuntimeException('The file is too large ('.round(strlen($content) / 1048576, 1).' MB; the limit is '.(self::MAX_BYTES / 1048576).' MB).');
        }
    }

    private static function sniff(string $content): ?string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);

        return is_string($mime) && $mime !== 'application/octet-stream' ? $mime : null;
    }
}
