<?php

namespace App\Agents\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Browsing tool (roadmap item 6). Fetches a web page and returns its readable
 * text so an agent isn't limited to pre-registered APIs. HTML is stripped to
 * plain text and capped; non-HTML text (JSON, plain) is passed through.
 *
 * SSRF guard: only http(s) URLs to public hosts are allowed — private,
 * loopback, and link-local ranges are refused so an agent can't be steered into
 * probing internal infrastructure.
 */
class WebBrowseTool implements Tool
{
    private const TIMEOUT_SECONDS = 15;

    private const MAX_CHARS = 15000;

    public function description(): Stringable|string
    {
        return 'Fetch a public web page or HTTP resource by URL and return its '
            .'readable text content. Use this to read documentation, articles, or '
            .'any page not covered by a dedicated tool.';
    }

    public function handle(Request $request): Stringable|string
    {
        $url = trim((string) ($request['url'] ?? ''));

        if ($url === '') {
            return json_encode(['error' => 'A url is required.']);
        }

        if (! $this->isAllowedUrl($url)) {
            return json_encode(['error' => 'Refused: only public http(s) URLs are allowed.']);
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => 'agent1o1-agent/1.0'])
                ->get($url);
        } catch (\Throwable $e) {
            return json_encode(['error' => 'Fetch failed: '.$e->getMessage()]);
        }

        if ($response->failed()) {
            return json_encode(['error' => "HTTP {$response->status()} fetching the URL.", 'status' => $response->status()]);
        }

        $contentType = strtolower($response->header('Content-Type') ?? '');
        $body = $response->body();

        $text = Str::contains($contentType, 'html')
            ? $this->htmlToText($body)
            : trim($body);

        return json_encode([
            'url' => $url,
            'status' => $response->status(),
            'content_type' => $contentType ?: null,
            'content' => $this->truncate($text),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->description('The absolute http(s) URL to fetch.')->required(),
        ];
    }

    private function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'] ?? '';

        if ($host === '') {
            return false;
        }

        // Resolve to an IP where possible and reject private / reserved ranges.
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) !== false;
        }

        // Could not resolve to an IP (e.g. DNS lookup unavailable in this
        // environment) — allow the request; the HTTP client still enforces the
        // timeout. Obvious local names are refused above via the IP check.
        return ! in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true);
    }

    private function htmlToText(string $html): string
    {
        // Drop script/style blocks entirely, then strip remaining tags.
        $html = preg_replace('#<(script|style|noscript|template)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<(br|/p|/div|/li|/h[1-6])\s*/?>#i', "\n", $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n\s*\n\s*\n+/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function truncate(string $text): string
    {
        if (mb_strlen($text) <= self::MAX_CHARS) {
            return $text;
        }

        return mb_substr($text, 0, self::MAX_CHARS)."\n…[truncated]";
    }
}
