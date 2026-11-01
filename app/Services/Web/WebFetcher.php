<?php

namespace App\Services\Web;

use App\Exceptions\Http\BlockedUrlException;
use App\Services\Http\GuardedHttp;
use App\Services\Http\SsrfGuard;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * Downloads a public web page for a workflow step, through the same outbound
 * guard as the Call API step: private and internal addresses are refused,
 * every redirect is checked again, and the response is size-capped.
 */
final class WebFetcher
{
    public function __construct(private readonly SsrfGuard $guard) {}

    /**
     * @return array{body: string, url: string, status: int, content_type: string}
     *
     * @throws RuntimeException
     */
    public function page(string $url, int $timeoutSeconds = 30): array
    {
        return $this->get($url, 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.5', ['html', 'xml', 'text'], $timeoutSeconds);
    }

    /**
     * @return array{body: string, url: string, status: int, content_type: string}
     *
     * @throws RuntimeException
     */
    public function feed(string $url, int $timeoutSeconds = 30): array
    {
        return $this->get($url, 'application/rss+xml,application/atom+xml,application/xml;q=0.9,text/xml;q=0.8,*/*;q=0.5', ['xml', 'rss', 'atom', 'text'], $timeoutSeconds);
    }

    /**
     * @param  list<string>  $allowedKinds  words the content type must contain one of
     * @return array{body: string, url: string, status: int, content_type: string}
     */
    private function get(string $url, string $accept, array $allowedKinds, int $timeoutSeconds): array
    {
        $url = trim($url);

        if (preg_match('#^https?://#i', $url) !== 1) {
            throw new RuntimeException('Enter a full web address starting with http:// or https://.');
        }

        try {
            $response = (new GuardedHttp($this->guard))->send('GET', $url, [
                'Accept' => $accept,
                'Accept-Language' => 'en',
                'User-Agent' => 'Mozilla/5.0 (compatible; AgentOneBot/1.0)',
            ], timeoutSeconds: $timeoutSeconds, retries: 1);
        } catch (BlockedUrlException $exception) {
            throw new RuntimeException($exception->getMessage());
        } catch (ConnectionException) {
            throw new RuntimeException('The page could not be reached. Check the address and try again.');
        }

        if (! $response->successful()) {
            throw new RuntimeException(match (true) {
                $response->status() === 404 => 'The page was not found (404).',
                in_array($response->status(), [401, 403], true) => "The site refused access ({$response->status()}). It may need a login or block automated visitors.",
                $response->status() === 429 => 'The site is limiting requests (429). Try again later.',
                default => "The page returned an error (HTTP {$response->status()}).",
            });
        }

        $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));

        if ($type !== '' && ! collect($allowedKinds)->contains(fn (string $kind): bool => str_contains($type, $kind))) {
            throw new RuntimeException("That address is not a web page it can read ({$type}).");
        }

        return ['body' => $response->body(), 'url' => $url, 'status' => $response->status(), 'content_type' => $type];
    }
}
