<?php

namespace App\Services\Workflows\Pages;

use App\Enums\Workflows\PageBlockType;
use App\Models\Workflows\WorkflowPage;
use Illuminate\Support\Str;

/**
 * What a link to a page shows when it's pasted into a chat app or a social
 * post: the title, description and image of its Open Graph card.
 *
 * Each part is the one the builder set for sharing, or else worked out from
 * the page: its name, the first paragraph of text on it, and its cover or
 * first image. Read from the published page, so the card matches what a
 * visitor will see.
 *
 * @phpstan-type Preview array{title: string, description: string, image_url: ?string, theme_color: string, site_name: string}
 */
class PageSharePreview
{
    public const int DESCRIPTION_LENGTH = 200;

    /**
     * @return Preview
     */
    public function for(WorkflowPage $page): array
    {
        $content = $page->published_content ?? $page->content;
        $workspace = $page->workspace?->name;

        return [
            'title' => $page->share_title ?: $page->name,
            'description' => $page->share_description
                ?: $this->firstParagraph(PageSchema::flatten($content['blocks']))
                ?? ($workspace ? "A page by {$workspace}. Fill it in to get your result in moments." : 'Fill it in to get your result in moments.'),
            'image_url' => $page->share_image_url ?: $this->image($content),
            'theme_color' => $content['theme']['accent'],
            'site_name' => $workspace ?: config('app.name'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function firstParagraph(array $blocks): ?string
    {
        foreach ($blocks as $block) {
            if ($block['type'] !== PageBlockType::Text->value) {
                continue;
            }

            $text = $this->plain($block['props']['markdown']);

            if ($text !== '') {
                return Str::limit($text, self::DESCRIPTION_LENGTH, '…', preserveWords: true);
            }
        }

        return null;
    }

    /**
     * @param  array{blocks: array<int, array<string, mixed>>, theme: array<string, mixed>}  $content
     */
    private function image(array $content): ?string
    {
        if (filled($content['theme']['cover_url'])) {
            return $content['theme']['cover_url'];
        }

        foreach (PageSchema::flatten($content['blocks']) as $block) {
            if ($block['type'] === PageBlockType::Image->value && filled($block['props']['url'])) {
                return $block['props']['url'];
            }
        }

        return null;
    }

    /**
     * Markdown as the plain sentence a link card shows, without its headings.
     */
    private function plain(string $markdown): string
    {
        $text = preg_replace([
            '/^\s{0,3}#{1,6}\s.*$/m',
            '/!\[[^\]]*\]\([^)]*\)/',
            '/\[([^\]]*)\]\([^)]*\)/',
            '/^\s{0,3}(>|[-*+]|\d+[.)])\s+/m',
            '/[*_`~]+/',
        ], ['', '', '$1', '', ''], $markdown) ?? '';

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
