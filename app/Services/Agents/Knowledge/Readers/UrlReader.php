<?php

namespace App\Services\Agents\Knowledge\Readers;

use App\Enums\Agents\KnowledgeSourceType;
use App\Exceptions\Http\BlockedUrlException;
use App\Models\Agents\KnowledgeSource;
use App\Services\Agents\Knowledge\KnowledgeBatch;
use App\Services\Agents\Knowledge\KnowledgeDocumentData;
use App\Services\Http\SsrfGuard;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A public web page, re-read on every sync. Every redirect hop is checked
 * by `SsrfGuard`, so a page can't point the server at an internal address.
 */
class UrlReader implements KnowledgeReader
{
    private const int MAX_REDIRECTS = 3;

    public function __construct(private readonly SsrfGuard $guard) {}

    public function type(): KnowledgeSourceType
    {
        return KnowledgeSourceType::Url;
    }

    public function configRules(): array
    {
        return ['config.url' => ['required', 'url:http,https', 'max:2000']];
    }

    public function read(KnowledgeSource $source, int $limit): KnowledgeBatch
    {
        $url = (string) ($source->config['url'] ?? '');
        $response = $this->fetch($url, self::MAX_REDIRECTS);

        if ($response->failed()) {
            throw new RuntimeException("The page answered with HTTP {$response->status()}.");
        }

        $body = $response->body();
        $isHtml = Str::contains((string) $response->header('Content-Type'), 'html') || Str::contains(Str::lower(Str::substr($body, 0, 500)), '<html');

        preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $title);

        return new KnowledgeBatch([new KnowledgeDocumentData(
            'page',
            html_entity_decode(trim($title[1] ?? '')) ?: $source->name,
            $isHtml ? $this->text($body) : $body,
            $url,
        )], complete: true);
    }

    private function fetch(string $url, int $redirectsLeft): Response
    {
        $this->guard->assertUrlIsAllowed($url);

        $response = Http::timeout(20)->withOptions(['allow_redirects' => false])->get($url);
        $location = (string) $response->header('Location');

        if (! $response->redirect() || $location === '') {
            return $response;
        }

        if ($redirectsLeft <= 0) {
            throw BlockedUrlException::forUrl($url, 'too many redirects.');
        }

        if (parse_url($location, PHP_URL_HOST) === null) {
            $base = parse_url($url);
            $location = "{$base['scheme']}://{$base['host']}".(isset($base['port']) ? ":{$base['port']}" : '').'/'.ltrim($location, '/');
        }

        return $this->fetch($location, $redirectsLeft - 1);
    }

    /**
     * Readable text of an HTML page: head, scripts, styles and navigation
     * dropped, block elements kept as paragraphs.
     */
    private function text(string $html): string
    {
        $html = preg_replace('#<(head|script|style|noscript|svg|nav|footer|header)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<(br|/p|/div|/h[1-6]|/li|/tr|/section|/article)\b[^>]*>#i', "\n\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;

        return trim(preg_replace('/\n\s*\n+/', "\n\n", $text) ?? $text);
    }
}
