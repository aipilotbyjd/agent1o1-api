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
    case FriendOrColleague = 'friend_or_colleague';
    case NewsletterOrBlog = 'newsletter_or_blog';
    case Podcast = 'podcast';
    case ProductHunt = 'product_hunt';
    case GitHub = 'github';
    case Facebook = 'facebook';
    case OnlineCourse = 'online_course';
    case ConferenceOrEvent = 'conference_or_event';
    case ReviewSite = 'review_site';
    case Advertisement = 'advertisement';
    case Other = 'other';

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
            self::FriendOrColleague => 'Friend or colleague',
            self::NewsletterOrBlog => 'Newsletter or blog post',
            self::Podcast => 'Podcast',
            self::ProductHunt => 'Product Hunt',
            self::GitHub => 'GitHub',
            self::Facebook => 'Facebook',
            self::OnlineCourse => 'Online course or tutorial',
            self::ConferenceOrEvent => 'Conference or event',
            self::ReviewSite => 'Review site (G2 / Capterra)',
            self::Advertisement => 'Advertisement',
            self::Other => 'Other',
        };
    }
}
