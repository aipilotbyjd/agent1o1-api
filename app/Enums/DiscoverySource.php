<?php

namespace App\Enums;

enum DiscoverySource: string
{
    case GoogleSearch = 'google_search';
    case ChatGptClaudePerplexity = 'chatgpt_claude_perplexity';
    case YouTube = 'youtube';
    case XTwitter = 'x_twitter';
    case LinkedIn = 'linkedin';
    case TikTokInstagram = 'tiktok_instagram';
    case RedditHackerNewsSlack = 'reddit_hackernews_slack';

    public function label(): string
    {
        return match ($this) {
            self::GoogleSearch => 'Google search',
            self::ChatGptClaudePerplexity => 'ChatGPT / Claude / Perplexity',
            self::YouTube => 'YouTube',
            self::XTwitter => 'X / Twitter',
            self::LinkedIn => 'LinkedIn',
            self::TikTokInstagram => 'TikTok / Instagram',
            self::RedditHackerNewsSlack => 'Reddit / Hacker News / Slack community',
        };
    }
}
