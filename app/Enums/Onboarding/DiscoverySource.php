<?php

namespace App\Enums\Onboarding;

enum DiscoverySource: string
{
    case GoogleSearch = 'google_search';
    case AiSearch = 'ai_search';
    case YouTube = 'youtube';
    case XTwitter = 'x_twitter';
    case LinkedIn = 'linkedin';
    case TikTokInstagram = 'tiktok_instagram';
    case Community = 'community';
    case Referral = 'referral';
    case Newsletter = 'newsletter';
    case Podcast = 'podcast';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::GoogleSearch => 'Google search',
            self::AiSearch => 'ChatGPT / Claude / Perplexity',
            self::YouTube => 'YouTube',
            self::XTwitter => 'X / Twitter',
            self::LinkedIn => 'LinkedIn',
            self::TikTokInstagram => 'TikTok / Instagram',
            self::Community => 'Reddit / Hacker News / Slack community',
            self::Referral => 'Friend or colleague',
            self::Newsletter => 'Newsletter or blog post',
            self::Podcast => 'Podcast',
            self::Other => 'Other',
        };
    }
}
