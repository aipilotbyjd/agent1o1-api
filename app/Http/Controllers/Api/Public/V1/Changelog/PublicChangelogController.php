<?php

namespace App\Http\Controllers\Api\Public\V1\Changelog;

use App\Enums\Changelog\ChangelogCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Public\V1\Changelog\ChangelogEntryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Changelog\ChangelogEntry;
use App\Services\Changelog\ChangelogReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * The public changelog: no session, no API key. Only released entries marked
 * `public` are ever returned; anything else answers 404 as if it didn't exist.
 * Responses are cacheable by browsers and CDNs for a minute.
 */
class PublicChangelogController extends Controller
{
    private const CACHE_CONTROL = 'public, max-age=60, stale-while-revalidate=300';

    private const FEED_SIZE = 50;

    public function __construct(private readonly ChangelogReader $reader) {}

    public function index(Request $request)
    {
        $request->validate([
            'category' => ['nullable', Rule::enum(ChangelogCategory::class)],
            'area' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:100'],
            'pinned' => ['nullable', 'boolean'],
        ]);

        $entries = $this->reader->filtered($this->reader->publicEntries(), $request)->paginate($this->perPage($request, 20, 50));

        return ApiResponse::paginated(ChangelogEntryResource::collection($entries))
            ->header('Cache-Control', self::CACHE_CONTROL);
    }

    public function show(Request $request, string $slug)
    {
        $entry = $this->reader->publicEntries()->where('slug', $slug)->firstOrFail();

        return ApiResponse::success((new ChangelogEntryResource($entry))->resolve($request))
            ->header('Cache-Control', self::CACHE_CONTROL);
    }

    public function rss(): Response
    {
        $entries = $this->latest();
        $site = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        $items = $entries->map(function (ChangelogEntry $entry) use ($site): string {
            $link = $this->e("{$site}/changelog/{$entry->slug}");

            return '<item>'
                .'<title>'.$this->e($entry->version !== null ? "{$entry->version}: {$entry->title}" : $entry->title).'</title>'
                ."<link>{$link}</link>"
                ."<guid isPermaLink=\"false\">{$entry->id}</guid>"
                .'<pubDate>'.$entry->published_at->toRfc2822String().'</pubDate>'
                .'<category>'.$this->e($entry->category->label()).'</category>'
                .'<description>'.$this->e($entry->summary).'</description>'
                .'<content:encoded><![CDATA['.str_replace(']]>', ']]]]><![CDATA[>', $entry->bodyHtml()).']]></content:encoded>'
                .'</item>';
        })->implode('');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
            .'<title>'.$this->e(config('app.name').' changelog').'</title>'
            ."<link>{$this->e($site.'/changelog')}</link>"
            .'<description>'.$this->e('What is new in '.config('app.name')).'</description>'
            .'<atom:link href="'.$this->e(url()->current()).'" rel="self" type="application/rss+xml"/>'
            .'<lastBuildDate>'.($entries->first()?->published_at ?? now())->toRfc2822String().'</lastBuildDate>'
            .$items
            .'</channel></rss>';

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8', 'Cache-Control' => self::CACHE_CONTROL]);
    }

    public function jsonFeed(): JsonResponse
    {
        $site = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return response()->json([
            'version' => 'https://jsonfeed.org/version/1.1',
            'title' => config('app.name').' changelog',
            'home_page_url' => "{$site}/changelog",
            'feed_url' => url()->current(),
            'items' => $this->latest()->map(fn (ChangelogEntry $entry): array => [
                'id' => $entry->id,
                'url' => "{$site}/changelog/{$entry->slug}",
                'title' => $entry->version !== null ? "{$entry->version}: {$entry->title}" : $entry->title,
                'summary' => $entry->summary,
                'content_html' => $entry->bodyHtml(),
                'date_published' => $entry->published_at->toRfc3339String(),
                'tags' => [$entry->category->value, ...($entry->areas ?? [])],
            ])->all(),
        ], 200, ['Content-Type' => 'application/feed+json; charset=UTF-8', 'Cache-Control' => self::CACHE_CONTROL]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, ChangelogEntry>
     */
    private function latest()
    {
        return $this->reader->publicEntries()->orderByDesc('published_at')->limit(self::FEED_SIZE)->get();
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
