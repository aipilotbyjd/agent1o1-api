<?php

namespace App\Engine\Trigger;

use App\Contracts\PayloadNormalizer;
use App\Engine\Trigger\Normalizers\AirtableNormalizer;
use App\Engine\Trigger\Normalizers\DiscordNormalizer;
use App\Engine\Trigger\Normalizers\GenericNormalizer;
use App\Engine\Trigger\Normalizers\GitHubNormalizer;
use App\Engine\Trigger\Normalizers\SlackNormalizer;
use App\Engine\Trigger\Normalizers\StripeNormalizer;

/**
 * Maps provider slugs to their payload normalizer. Unknown providers fall back
 * to the GenericNormalizer.
 *
 * To add a new provider: create a normalizer in app/Engine/Trigger/Normalizers/
 * and add an entry to NORMALIZERS below.
 */
class NormalizerRegistry
{
    /** @var array<string, class-string<PayloadNormalizer>> */
    private const NORMALIZERS = [
        'github' => GitHubNormalizer::class,
        'slack' => SlackNormalizer::class,
        'stripe' => StripeNormalizer::class,
        'discord' => DiscordNormalizer::class,
        'airtable' => AirtableNormalizer::class,
        'generic' => GenericNormalizer::class,
    ];

    public function resolve(string $provider): PayloadNormalizer
    {
        $class = self::NORMALIZERS[$provider] ?? GenericNormalizer::class;

        return app($class);
    }

    public static function supports(string $provider): bool
    {
        return isset(self::NORMALIZERS[$provider]);
    }
}
